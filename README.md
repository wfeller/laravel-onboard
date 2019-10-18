# Laravel Onboard
A Laravel package to help track user onboarding steps.

##### Based on [calebporzio/onboard](https://github.com/calebporzio/onboard).

## Installation:

* Install the package via composer
```bash
composer require wfeller/laravel-onboard
```
* Add the `WF\Onboard\GetsOnboarded` trait to your app's User model
```php
class User extends Model
{
    use \WF\Onboard\GetsOnboarded;
    // ...
}
```

## Example Configuration:

Configure your steps in your `App\Providers\AppServiceProvider.php`
```php
    public function boot()
    {
        // This step will only apply to User::class:
        OnboardFacade::addStep('Create your first post', User::class)
            ->link('/post/create')
            ->cta('Create Post')
            // ->cacheResults() // You can cache the results to avoid duplicating queries
            ->completeIf(function (User $user) {
                // This will make 1 DB query each time to retrieve the count
                // The result will be cached if using cacheResults()
                return $user->posts()->count() > 0;
            })
            // You may add a scope to only fetch users having completed this step
            // This scope will be used when querying User::onboarded()->get()
            ->completeScope(function (Builder $builder) {
                $builder->whereHas('posts');
            })
            // All steps are required by default for all users, but you can change this behaviour
            ->requiredIf(function (User $user) {
                return $user->type === 'writer';
            })
            // You may translate your required method into a DB where clause.
            // If you don't, your completeScope (i.e. $builder->whereHas('posts')) will be called on all Users
            ->requiredScope(function (Builder $builder) {
                $builder->where('type', 'writer');
            });
    }   
```
## Usage:
Now you can access these steps along with their state wherever you like. Here is an example blade template:
```php
@if (Auth::user()->onboarding()->inProgress())
    <div>

        @foreach (Auth::user()->onboarding()->steps as $step)
            <span>
                @if($step->complete())
                    <i class="fa fa-check-square-o fa-fw"></i>
                    <s>{{ $loop->iteration }}. {{ $step->title }}</s>
                @else
                    <i class="fa fa-square-o fa-fw"></i>
                    {{ $loop->iteration }}. {{ $step->title }}
                @endif
            </span>
                        
            <a href="{{ $step->link }}" {{ $step->complete() ? 'disabled' : '' }}>
                {{ $step->cta }}
            </a>
        @endforeach

    </div>
@endif
```
Check out all the available features below:
```php
User::onboarded()->get()->each->notify(new OnboardingComplete);
// User::onboarded(true by default)
User::onboarded(false)->get()->each->notify(new OnboardingIncomplete);

$onboarding = Auth::user()->onboarding();

$onboarding->inProgress();
$onboarding->finished();
$onboarding->finishedRequired();
$onboarding->nextUnfinishedStep();
$onboarding->step($code); // returns the step you're looking for

$onboarding->steps()->each(function($step) {
    $step->code;
    $step->title;
    $step->cta;
    $step->link;
    $step->complete();
    $step->incomplete();
    $step->required();
    $step->optional();
});
```
Definining custom attributes and accessing them:
```php
// Defining the attributes
// Closures will be resolved using the given onboarding user as their only argument 
OnboardFacade::addStep('Step w/ custom attributes', User::class)
    ->setAttributes([
        'name' => 'Waldo',
        'shirt_color' => 'Red & White',
        'shirt_price' => function (User $user) {
            return $user->age * 4; // yes, that example sucks :)
        },
    ]);

// Accessing them
$step->name;
$step->shirt_color;
$step->shirt_price;
```

## Example middleware

If you want to ensure that your user is redirected to the next 
unfinished onboarding step, whenever they access your web application,  
you can use the following middleware as a starting point:

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Auth;
use Closure;

class RedirectToUnfinishedOnboardingStep
{
    public function handle($request, Closure $next)
    {
        if (Auth::user()->onboarding()->inProgress()) {
            return redirect()->to(
                Auth::user()->onboarding()->nextUnfinishedStep()->link
            );
        }
        
        return $next($request);
    }
}
```

**Quick tip**: Don't add this middleware to routes that update the state 
of the onboarding steps, your users will not be able to progress because they will be redirected back to the onboarding step.
