<?php

namespace WF\Onboard\Test;

use WF\Onboard\OnboardFacade;
use WF\Onboard\OnboardingSteps;
use WF\Onboard\OnboardingManager;
use WF\Onboard\Test\Models\Company;
use WF\Onboard\Test\Models\User;
use Illuminate\Database\Eloquent\Builder;
use stdClass;

class OnboardTest extends TestCase
{
    /** @test */
    public function steps_can_be_defined_and_configured()
    {
        $onboardingSteps = new OnboardingSteps;

        $onboardingSteps->addStep('Test Step')
            ->link('/some/url')
            ->cta('Test This!')
            ->attributes(['another' => 'attribute'])
            ->completeIf($this->boolCallable(true));

        $this->assertEquals(1, $onboardingSteps->steps(new stdClass)->count());

        $step = $onboardingSteps->steps(new stdClass)->first();

        $this->assertEquals('/some/url', $step->link);
        $this->assertEquals('Test This!', $step->cta);
        $this->assertEquals('Test Step', $step->title);
        $this->assertEquals('attribute', $step->another);
    }

    /** @test */
    public function steps_can_be_scoped_to_a_class()
    {
        $steps = new OnboardingSteps;
        $steps->addStep('Step', User::class);
        $steps->addStep('Step', [User::class, null]);
        $steps->addStep('Step', [User::class, Company::class]);
        $this->assertEquals(1, $steps->steps(new stdClass)->count());
        $this->assertEquals(1, $steps->steps(new Company)->count());
        $this->assertEquals(3, $steps->steps(new User)->count());
    }

    /** @test */
    public function is_in_progress_when_all_steps_are_incomplete()
    {
        $onboardingSteps = new OnboardingSteps;
        $onboardingSteps->addStep('Test Step');
        $onboardingSteps->addStep('Another Test Step');

        $onboarding = new OnboardingManager(new User, $onboardingSteps);

        $this->assertTrue($onboarding->inProgress());
        $this->assertFalse($onboarding->finished());
        $this->assertFalse($onboarding->finishedRequired());
    }

    /** @test */
    public function is_finished_when_all_steps_are_complete()
    {
        $onboardingSteps = new OnboardingSteps;
        $onboardingSteps->addStep('Test Step')->completeIf($this->boolCallable(true));
        $onboarding = new OnboardingManager(new User, $onboardingSteps);

        $this->assertTrue($onboarding->finished());
        $this->assertTrue($onboarding->finishedRequired());
        $this->assertFalse($onboarding->inProgress());
    }

    /** @test */
    public function finished_required_when_all_required_steps_are_complete()
    {
        $onboardingSteps = new OnboardingSteps;
        $onboardingSteps->addStep('First Step')->completeIf($this->boolCallable(true))->requiredIf($this->boolCallable(true));
        $onboardingSteps->addStep('Second Step')->completeIf($this->boolCallable(false))->requiredIf($this->boolCallable(false));

        $onboarding = new OnboardingManager(new User, $onboardingSteps);

        $this->assertFalse($onboarding->finished());
        $this->assertTrue($onboarding->finishedRequired());
        $this->assertTrue($onboarding->inProgress());
    }

    /** @test */
    public function it_returns_the_correct_next_unfinished_step()
    {
        $onboardingSteps = new OnboardingSteps;
        $onboardingSteps->addStep('Step 1')->link("/step-1")->completeIf($this->boolCallable(true));
        $onboardingSteps->addStep('Step 2')->link("/step-2")->completeIf($this->boolCallable(false));
        $onboardingSteps->addStep('Step 3')->link("/step-3")->completeIf($this->boolCallable(false));

        $onboarding = new OnboardingManager(new User, $onboardingSteps);

        $nextStep = $onboarding->nextUnfinishedStep();

        $this->assertNotNull($nextStep);
        $this->assertEquals("Step 2", $nextStep->title);
        $this->assertEquals("/step-2", $nextStep->link);
    }

    /** @test */
    public function nextUnfinishedStep_returns_null_if_all_steps_are_completed()
    {
        $onboardingSteps = new OnboardingSteps;
        $onboardingSteps->addStep('Step 1')->completeIf($this->boolCallable(true));
        $onboardingSteps->addStep('Step 2')->completeIf($this->boolCallable(true));
        $onboardingSteps->addStep('Step 3')->completeIf($this->boolCallable(true));

        $onboarding = new OnboardingManager(new User, $onboardingSteps);

        $nextStep = $onboarding->nextUnfinishedStep();

        $this->assertNull($nextStep);
    }

    /** @test */
    public function the_proper_object_gets_passed_into_completion_callback()
    {
        $user = new User;
        $user->name = 'joe';

        $onboardingSteps = new OnboardingSteps;
        $onboardingSteps->addStep('Test Step')
            ->completeIf(function ($user) {
                return $user->name === 'joe';
            });

        $onboarding = new OnboardingManager($user, $onboardingSteps);

        $this->assertTrue($onboarding->finished());
    }

    /** @test */
    public function can_query_onboarded_users()
    {
        $this->assertEquals(0, User::onboarded()->count());
        /** @var OnboardingSteps $steps */
        $steps = $this->app->get(OnboardingSteps::class);
        OnboardFacade::addStep('Required', User::class)
            ->requiredIf($this->boolCallable(true))
            ->completeScope(function (Builder $builder) {
                $builder->orWhere('age', 1);
                $builder->orWhere('age', 2);
                $builder->orWhere('age', 3);
                $builder->orWhere('age', 100);
            });

        $steps->addStep('Sometimes Required', User::class)
            ->requiredIf(function (User $user) {
                return in_array($user->name, ['john', 'joe']);
            })
            ->requiredScope(function (Builder $builder) {
                $builder->orWhere('name', 'john')->orWhere('name', 'joe');
            })
            ->completeScope(function (Builder $builder) {
                $builder->orWhere('age', 3);
                $builder->orWhere('age', 4);
                $builder->orWhere('age', 5);
            });

        $steps->addStep('Robert Age 100 - Alcoholic', User::class)
            ->requiredIf(function (User $user) {
                return $user->name === 'robert' && $user->age === 100;
            })
            ->requiredScope(function (Builder $builder) {
                $builder->where('name', 'robert')->where('age', 100);
            })
            ->completeScope(function (Builder $builder) {
                //
            });

        User::query()->insert([
            ['name' => 'doesnt matter', 'age' => 10], // not complete
            ['name' => 'john', 'age' => 10], // not complete
            ['name' => 'john', 'age' => 4], // not complete
            ['name' => 'doesnt matter', 'age' => 1], // complete
            ['name' => 'john', 'age' => 3], // complete
            ['name' => 'robert', 'age' => 100], // complete
            ['name' => 'robert', 'age' => 101], // not complete
        ]);

        $this->assertEquals(3, User::onboarded()->count());
        $this->assertEquals(7, User::query()->count());
    }

    /** @test */
    public function can_use_scope_in_onboarded_scope()
    {
        $this->assertEquals(0, User::onboarded()->count());
        /** @var OnboardingSteps $steps */
        OnboardFacade::addStep('Required', User::class)
            ->completeScope(function (Builder $builder) {
                /** @var User $builder */
                $builder->named('robert');
            })
            ->completeIf(function (User $user) {
                return $user->name === 'robert';
            })
            ->requiredScope(function (Builder $builder) {
                /** @var User $builder */
                $builder->hasAnyName('robert', 'john');
            })
        ;

        User::query()->insert([
            ['name' => 'john', 'age' => 10], // not complete
            ['name' => 'robert', 'age' => 100], // complete
        ]);

        $robert = User::named('robert')->first();

        $this->assertEquals(1, User::onboarded()->count());
        $this->assertTrue($robert->is(User::onboarded()->first()));
    }

    /** @test */
    public function can_use_scope_in_onboarded_scope_2()
    {
        $this->assertEquals(0, User::onboarded()->count());
        /** @var OnboardingSteps $steps */
        OnboardFacade::addStep('Required', User::class)
            ->completeScope(function (Builder $builder) {
                /** @var User $builder */
                $builder->hasAnyName('robert');
            })
            ->completeIf(function (User $user) {
                return $user->name === 'robert';
            })
            ->requiredScope(function (Builder $builder) {
                /** @var User $builder */
                $builder->hasAnyName('robert', 'john');
            })
        ;

        User::query()->insert([
            ['name' => 'john', 'age' => 10], // not complete
            ['name' => 'robert', 'age' => 100], // complete
        ]);

        $robert = User::named('robert')->first();

        $this->assertEquals(1, User::onboarded()->count());
        $this->assertTrue($robert->is(User::onboarded()->first()));
    }

    private function boolCallable(bool $return = true)
    {
        return function () use ($return) {
            return $return;
        };
    }
}
