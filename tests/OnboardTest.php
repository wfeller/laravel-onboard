<?php

namespace WF\Onboard\Test;

use WF\Onboard\OnboardFacade;
use WF\Onboard\OnboardingStep;
use WF\Onboard\OnboardingSteps;
use WF\Onboard\OnboardingManager;
use WF\Onboard\Test\Models\Company;
use WF\Onboard\Test\Models\User;
use Illuminate\Database\Eloquent\Builder;
use stdClass;

class OnboardTest extends TestCase
{
    public function test_steps_can_be_defined_and_configured()
    {
        $onboardingSteps = new OnboardingSteps;

        $onboardingSteps->addStep('Test Step', stdClass::class)
            ->link('/some/url')
            ->cta('Test This!')
            ->title(function (stdClass $class) {
                return $class->title;
            })
            ->setAttributes(['another' => 'attribute'])
            ->completeIf($this->boolCallable(true));

        $onboardingSteps->addStep('Other Step', stdClass::class);

        $class = new stdClass;
        $class->title = 'My Great Title';

        $this->assertEquals(2, $onboardingSteps->steps($class)->count());

        $step = $onboardingSteps->steps($class)->first();
        $last = $onboardingSteps->steps($class)->last();

        $this->assertEquals('/some/url', $step->link);
        $this->assertEquals('Test This!', $step->cta);
        $this->assertEquals('Test Step', $step->code);
        $this->assertEquals('My Great Title', $step->title);
        $this->assertEquals('attribute', $step->another);

        $this->assertEquals('Other Step', $last->code);
        $this->assertEquals('Other Step', $last->title);
    }

    public function test_steps_can_be_scoped_to_a_class()
    {
        $steps = new OnboardingSteps;
        $steps->addStep('Step', User::class);
        $steps->addStep('Step', Company::class);
        $this->assertEquals(1, $steps->steps(new Company)->count());
        $this->assertEquals(1, $steps->steps(new User)->count());
    }

    public function test_steps_can_exist_only_once_per_code()
    {
        $steps = new OnboardingSteps;
        $steps->addStep('Step', User::class)->setAttributes(['letter' => 'a']);
        $steps->addStep('Step', User::class)->setAttributes(['letter' => 'b']);
        $this->assertEquals(1, $steps->steps(new User)->count());
        $this->assertEquals('b', $steps->steps(new User)->get('Step')->letter);
    }

    public function test_steps_can_be_correctly_scoped_to_a_class()
    {
        $mapper = function (OnboardingStep $step) {
            return ['code' => $step->code, 'letter' => $step->getAttribute('letter')];
        };

        $steps = new OnboardingSteps;
        $steps->addStep('step 1', User::class)->setAttributes(['letter' => 'a']);
        $steps->addStep('step 2', User::class)->setAttributes(['letter' => 'b']);
        $steps->addStep('step 3', Company::class)->setAttributes(['letter' => 'c']);
        $this->assertEquals(1, $steps->steps(new Company)->count());
        $this->assertEquals(2, $steps->steps(new User)->count());

        $company = $steps->steps(new Company);
        $this->assertEquals(['step 3' => ['code' => 'step 3', 'letter' => 'c']], $company->map($mapper)->all());

        $user1 = $steps->steps(new User);
        $this->assertEquals([
            'step 1' => ['code' => 'step 1', 'letter' => 'a'],
            'step 2' => ['code' => 'step 2', 'letter' => 'b'],
        ], $user1->map($mapper)->all());

        $user2 = $steps->steps(new User);

        $user2->get('step 2')->setAttributes(['letter' => 'x']);
        $this->assertEquals('b', $user1->get('step 2')->letter);
        $this->assertEquals('x', $user2->get('step 2')->letter);
    }

    public function test_can_differently_update_multiple_onboarding_steps()
    {
        OnboardFacade::addStep('code', User::class);
        $user_a = new User;
        $step_a = $user_a->onboarding()->step('code');
        $step_a_a = $user_a->onboarding()->step('code');
        $user_b = new User;
        $step_b = $user_b->onboarding()->step('code');
        $step_a->title('updated');
        $step_a_b = $user_a->onboarding()->step('code');
        $this->assertEquals('code', $step_b->title);
        $this->assertEquals('updated', $step_a->title);
        $this->assertSame($step_a, $step_a_a);
        $this->assertSame($step_a, $step_a_b);
        $this->assertNotSame($step_a, $step_b);
        $this->assertEquals('updated', $step_a_a->title);
        $this->assertEquals('updated', $step_a_b->title);
    }

    public function test_always_returns_the_same_steps_for_the_same_user()
    {
        User::query()->create(['name' => 'joe', 'age' => 30]);
        OnboardFacade::addStep('code', User::class);
        $user_a = User::query()->first();
        $step_a = $user_a->onboarding()->step('code');
        $user_b = User::query()->first();
        $step_b = $user_b->onboarding()->step('code');
        $this->assertSame($step_a, $step_b);
    }

    public function test_is_in_progress_when_all_steps_are_incomplete()
    {
        $onboardingSteps = new OnboardingSteps;
        $onboardingSteps->addStep('Test Step', User::class);
        $onboardingSteps->addStep('Another Test Step', User::class);

        $onboarding = new OnboardingManager(new User, $onboardingSteps);

        $this->assertTrue($onboarding->inProgress());
        $this->assertFalse($onboarding->finished());
        $this->assertFalse($onboarding->finishedRequired());
    }

    public function test_is_finished_when_all_steps_are_complete()
    {
        $onboardingSteps = new OnboardingSteps;
        $onboardingSteps->addStep('Test Step', User::class)->completeIf($this->boolCallable(true));
        $onboarding = new OnboardingManager(new User, $onboardingSteps);

        $this->assertTrue($onboarding->finished());
        $this->assertTrue($onboarding->finishedRequired());
        $this->assertFalse($onboarding->inProgress());
    }

    public function test_finished_required_when_all_required_steps_are_complete()
    {
        $onboardingSteps = new OnboardingSteps;
        $onboardingSteps->addStep('First Step', User::class)->completeIf($this->boolCallable(true))->requiredIf($this->boolCallable(true));
        $onboardingSteps->addStep('Second Step', User::class)->completeIf($this->boolCallable(false))->requiredIf($this->boolCallable(false));

        $onboarding = new OnboardingManager(new User, $onboardingSteps);

        $this->assertFalse($onboarding->finished());
        $this->assertTrue($onboarding->finishedRequired());
        $this->assertTrue($onboarding->inProgress());
    }

    public function test_it_returns_the_correct_next_unfinished_step()
    {
        $onboardingSteps = new OnboardingSteps;
        $onboardingSteps->addStep('Step 1', User::class)->link("/step-1")->completeIf($this->boolCallable(true));
        $onboardingSteps->addStep('Step 2', User::class)->link("/step-2")->completeIf($this->boolCallable(false));
        $onboardingSteps->addStep('Step 3', User::class)->link("/step-3")->completeIf($this->boolCallable(false));

        $onboarding = new OnboardingManager(new User, $onboardingSteps);

        $nextStep = $onboarding->nextUnfinishedStep();

        $this->assertNotNull($nextStep);
        $this->assertEquals("Step 2", $nextStep->title);
        $this->assertEquals("/step-2", $nextStep->link);
    }

    public function test_nextUnfinishedStep_returns_null_if_all_steps_are_completed()
    {
        $onboardingSteps = new OnboardingSteps;
        $onboardingSteps->addStep('Step 1', User::class)->completeIf($this->boolCallable(true));
        $onboardingSteps->addStep('Step 2', User::class)->completeIf($this->boolCallable(true));
        $onboardingSteps->addStep('Step 3', User::class)->completeIf($this->boolCallable(true));

        $onboarding = new OnboardingManager(new User, $onboardingSteps);

        $nextStep = $onboarding->nextUnfinishedStep();

        $this->assertNull($nextStep);
    }

    public function test_can_search_a_step_by_code()
    {
        $step1 = OnboardFacade::addStep('code 1', User::class);
        $step2 = OnboardFacade::addStep('code 2', User::class);
        $step3 = OnboardFacade::addStep('code 3', User::class);

        $user = new User;
        $this->assertNull($user->onboarding()->step('code 4'));
        $this->assertSame($step1->code, $user->onboarding()->step('code 1')->code);
        $this->assertSame($step2->code, $user->onboarding()->step('code 2')->code);
        $this->assertSame($step3->code, $user->onboarding()->step('code 3')->code);
    }

    public function test_the_proper_object_gets_passed_into_completion_callback()
    {
        $user = new User;
        $user->name = 'joe';

        $onboardingSteps = new OnboardingSteps;
        $onboardingSteps->addStep('Test Step', User::class)
            ->completeIf(function (User $user) {
                return $user->name === 'joe';
            });

        $onboarding = new OnboardingManager($user, $onboardingSteps);

        $this->assertTrue($onboarding->finished());
    }

    public function test_can_query_onboarded_users()
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
        $this->assertEquals(4, User::onboarded(false)->count());
        $this->assertEquals(7, User::query()->count());
    }

    public function test_can_use_scope_in_onboarded_scope()
    {
        $this->assertEquals(0, User::onboarded()->count());

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
            });

        User::query()->insert([
            ['name' => 'john', 'age' => 10], // not complete
            ['name' => 'robert', 'age' => 100], // complete
        ]);

        $robert = User::named('robert')->first();

        $this->assertEquals(1, User::onboarded()->count());
        $this->assertTrue($robert->is(User::onboarded()->first()));
    }

    public function test_can_use_scope_in_onboarded_scope_2()
    {
        $this->assertEquals(0, User::onboarded()->count());

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
            });

        User::query()->insert([
            ['name' => 'john', 'age' => 10], // not complete
            ['name' => 'robert', 'age' => 100], // complete
        ]);

        $robert = User::named('robert')->first();

        $this->assertEquals(1, User::onboarded()->count());
        $this->assertTrue($robert->is(User::onboarded()->first()));
    }

    public function test_a_step_can_be_never_required()
    {
        $this->assertEquals(0, User::onboarded()->count());

        OnboardFacade::addStep('Never Required', User::class)
            ->neverRequired()
            ->completeScope(function (Builder $builder) {
                /** @var User $builder */
                $builder->hasAnyName('robert');
            })
            ->completeIf(function (User $user) {
                return $user->name === 'robert';
            });

        User::query()->insert([
            ['name' => 'John', 'age' => 10], // not complete
            ['name' => 'Jack', 'age' => 100], // not complete
        ]);

        $this->assertEquals(2, User::onboarded()->count());

        /** @var User $jack */
        $jack = User::query()->where('name', 'Jack')->first();

        $this->assertFalse($jack->onboarding()->finished());
        $this->assertTrue($jack->onboarding()->finishedRequired());
    }

    public function test_required_and_complete_results_can_be_cached()
    {
        OnboardFacade::addStep('WithCache', User::class)
            ->cacheResults()
            ->completeIf(function (User $user) {
                return $user->name === 'robert';
            });

        OnboardFacade::addStep('WithoutCache', User::class)
            ->completeIf(function (User $user) {
                return $user->name === 'robert';
            });

        $robert = new User(['name' => 'robert', 'age' => 50]);
        $robert->save();

        $this->assertTrue($robert->onboarding()->finished());

        $this->assertTrue($robert->onboarding()->step('WithCache')->complete());
        $this->assertTrue($robert->onboarding()->step('WithoutCache')->complete());

        $robert->name = 'bob';

        $this->assertTrue($robert->onboarding()->step('WithCache')->complete());
        $this->assertFalse($robert->onboarding()->step('WithoutCache')->complete());

        $robert->save();

        /** @var User $robertCopy */
        $robertCopy = User::query()->find($robert->getKey());

        $this->assertTrue($robertCopy->onboarding()->step('WithCache')->complete());
        $this->assertFalse($robertCopy->onboarding()->step('WithoutCache')->complete());

        $robert->onboarding()->resetCache();

        $this->assertFalse($robert->onboarding()->step('WithCache')->complete());
        $this->assertFalse($robert->onboarding()->step('WithoutCache')->complete());

        $jane = new User(['name' => 'jane']);

        $this->assertTrue($jane->onboarding()->inProgress());

        $this->assertTrue($jane->onboarding()->step('WithCache')->incomplete());
        $this->assertTrue($jane->onboarding()->step('WithoutCache')->incomplete());
    }

    private function boolCallable(bool $return = true) : \Closure
    {
        return static fn () : bool => $return;
    }
}
