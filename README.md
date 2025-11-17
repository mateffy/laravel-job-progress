<div>
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="./docs/elephant-dark.svg">
      <img src="./docs/elephant.svg">
    </picture>
</div>

# Job Progress for Laravel Queues

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mateffy/laravel-job-progress.svg?style=flat-square)](https://packagist.org/packages/mateffy/laravel-job-progress) ![Test Status](https://img.shields.io/github/check-runs/mateffy/laravel-job-progress/main?style=flat-square)


Track and show progress of your background jobs (for progress bar UIs etc.) using Laravel's cache system. Also supports cancelling jobs during execution and also returning some job result data (e.g. model IDs or other DTOs).

<br />

- [Installation](#installation)
- [Updating the progress](#updating-the-progress)
- [Accessing the progress outside the job](#accessing-the-progress-outside-the-job)
- [Cancelling a job](#cancelling-a-job)
- [Progress Lifecycle](#progress-lifecycle)
- [Defining Progress IDs](#defining-progress-ids)
- [Customizing Cache Options](#customizing-cache-options)
- [Frequently Asked Questions](#frequently-asked-questions)

<br />

## Installation

You can install the package via composer:

```bash
composer require mateffy/laravel-job-progress
```

Then, implement the `HasJobProgress` interface with the help of the `Progress` trait:

```php
use Mateffy\JobProgress\Contracts\HasJobProgress;
use Mateffy\JobProgress\Traits\Progress;

class MyJob implements ShouldQueue, HasJobProgress
{
    use Queueable;
    use Progress;

    /** 
     * Return a unique ID for this job instance, 
     * using which you can access progress outside the job 
     */
    public function getProgressId(): string {}

    /** 
     * Implement your job handler here.
     */
    public function handleWithProgress(): void {}
}
```

<br />

## Updating the progress

Inside a job handler, you have access to the `$this->progress()` method, returning an up-to-date `JobState` object.
This is the primary way to interact with job progress from inside a running job.

```php
public function handleWithProgress(): void
{
    $request = Http::get('...');
    
    // If the job failed, simply throw an exception.
    // The job status will be updated as `failed` accordingly.
    if ($request->failed()) {
        throw new Exception('Request failed');
    }
    
    $this->progress()->update(0.5); // Set progress to 50%
    
    $saved = MyModel::createFromData($request->json());
    
    // Progress is completed automatically when the job finished.
    // Optionally, if you want to associate some piece of data (for example the saved model) to work with later,
    // you can manually mark the progress as completed too.
    $this->progress()->complete(result: $saved);
}
```

### List-based progress

If you're working with a list of items, the package provides a simple helper method to update the progress based on the current item index and the total number of items.
Using this method, you don't need to manually calculate the progress percentage yourself and can avoid dealing with math errors (e.g. division by zero).

```php
public function handleWithProgress(): void
{
    $articles = News::recent();
    
    $this->progress()->update(0.25); // Set progress to 50%
    
    foreach ($articles as $index => $item) {
    	News::postToSocialMedia($item);
    	
    	$this->progress()->updateWithSteps(
    	    completed: $index + 1,
    	    total: count($articles),
    	    base: 0.25, // continue the progress at 25% (which is what we set after data fetching)
    	    max: 0.5, // this part of the job can "take up" 50% of the total progress
    	)
    }
    
    // Progress is now at 75%
    
    News::sendNewsletter();
}
```

### Marking as complete

You don't need to manually mark a job as `completed`, as this will be done automatically if the job handler finishes without errors. If you do wish to do so manually (e.g. to add output/result data) you can use the `complete` method.

```php
$this->progress()->complete();

// With some data attached:
$this->progress()->complete(result: $result);
```

### Marking as failed

You don't need to manually catch exceptions and mark the job as failed. If an exception is thrown, the job will be marked as failed automatically by the `Progress` trait. If you know what you're doing and want to mark a job as failed manually, you can use the `fail` method, which accepts an error message.

**However, I recommend simply throwing an exception instead of manually marking the job as failed, as this leads to better error reporting with other systems (e.g. Flare, Nightwatch, etc.) and also triggers the normal Laravel queue retry logic.

```php
// Mark as failed by throwing an exception inside the job:
throw new Exception('This will automatically mark the job as failed');

// If doing soething custom or from outside the job:
$this->progress()->fail(error: 'Something went wrong');
```

<br />

## Accessing the progress outside the job

You can access the job state from outside the job by using the `getProgress` on the job class.
All you need to know is the unique job ID (not the full cache key).

```php
use \Mateffy\JobProgress\Data\JobState;

$id = uniqid();
MyJob::dispatch(id: $id);

/** @var ?JobState $state */
$state = MyJob::getProgress($id);
$state->progress; // float
$state->status; // JobStatus enum
$state->result; // mixed, your own custom result data
$state->error; // ?string, error message if the job failed
```

### Locking jobs

The `pending` status can be used to obtain a lock on a job, preventing it from being executed multiple times. Using the `Job::lock($id)` method makes this super easy to setup when dispatching the job.

```php
if (MyJob::lock($id)) {
    MyJob::dispatch($id, ...);
}
```

The `lock` method will return `null` if any state _already exists_ (even if only `pending`). Otherwise it will create new pending state and return it.

Note that the lock only applies to the progress ID and will only be locked **until it is completed, failed or cancelled**. The same job class can still execute multiple times / in parallel with different IDs. IF you want the job to be entirely unique, make sure your IDs are globally unique or use the default [Laravel job locks](https://laravel.com/docs/12.x/queues#unique-jobs).

<br />

## Cancelling a job

This package supports job cancellation.
This allows a user or the system to cancel a job while it's still running, stopping it from completing.
Generally, this is a very helpful feature for users to cancel long-running jobs that were started on accident,
or re-start jobs that are stalled.
However, cancelling jobs also has a few caveats that you should be aware of too.

If you want your job to support cancellation, you need to add the `#[Cancellable]` attribute and "cancellation checkpoints" to your job code.
These are places where the job checks if it was cancelled, and continues or stops accordingly.
This way, you retain full control over when a job can actually be stopped, eliminating the possibility of invalid data. For example, you may only want to support cancellation before any data is written to the database or any irreversible changes are made.

```php
#[Cancellable]
class MyJob implements ShouldQueue, HasJobProgress
{
    // ...
    
    public function handleWithProgress(): void
    {
        $articles = News::recent();
        $comments = [];
        
        foreach ($articles as $index => $article) {
            // Check if the job was cancelled, and exit if so
            $this->progress()
                ->updateWithSteps(completed: $index + 1, total: count($articles), max: 0.5)
                ->exitIfCancelled(); 
           
            $comments = [...$articles, ...News::comments($article)];
        }
        
        // Check for cancellation one last time, as the job may have been cancelled after the last iteration
        $this->progress()
            ->update(0.5)
            ->exitIfCancelled();
        
        // Now that we're persisting data, we no longer include checkpoints, forcing the job to complete all the way from now on.
        // We could call this the "point of no return" or "event horizon" if you're extra nerdy
        
        $persisted = MyModel::createFromData($request->json());
        
        $this->progress()->update(0.8);
        
        // ...even more work...
    }
}
```

To cancel a job, you can simply call the `cancel` method on the job state.
After calling, the job will exit as soon as possible, without completing any further work.

```php
/** @var \Mateffy\JobProgress\Data\JobState $state */
$state = MyJob::getProgress($id);
$state->cancel();
```

> [!IMPORTANT]
> It is up to your own code to ensure that the job is cancelled properly. This includes handling any cleanup tasks that may be necessary. 

### Making jobs uncancellable after a specific point

From the outside (frontend etc.) your `#[Cancellable]` job can _always_ be marked as cancelled, until it is complete or has failed. This won't affect the job's _actual_ cancellation (since you're implementing this yourself anyway), but can be confusing from a UX perspective (job looks cancelled, but it's not).

If your job is performing some irreversible operations, you can mark it as `uncancellable` after a certain amount of progress has passed using the `#[Cancellable(threshold: float)]` parameter. This parameter defaults to `1.0` (100%). 

```php
// Mark job as uncancellable if progress >= 75%
#[Cancellable(threshold: 0.75)]
class MyJob extends Job implements Progressable
{
    use Progress;
    // ...
}
```

<br />

## Progress Lifecycle

The progress goes through multiple steps as it's executed.
These are indicated by the `JobStatus` enum, available using `$state->status`.

| Status | Description |
| --- | --- |
| JobStatus::Pending | The job is waiting to be executed. |
| JobStatus::Processing | The job is currently running. |
| JobStatus::Completed | The job has completed successfully. |
| JobStatus::Failed | The job has failed. An `$error` message is available. |
| JobStatus::Cancelled | The job has been cancelled. |

You don't have to manually mark jobs as `processing`, `completed` or `failed`, as the `Progress` trait and `handleWithProgress` method will take care of this for you (e.g. by catching exceptions).

<br />

## Defining Progress IDs

Each job instance needs to have a unique ID which can be used to track the progress of the job.
Mainly, there are two ways to define/work with this ID:

- Using a reproducible ID (e.g. a hash of another ID or of the job parameters)
- Using a random ID (e.g. `Str::uuid()` or `uniqid()`)


### Using a reproducible ID

A reproducible ID is an identifier derived from a piece of already known data (e.g. another ID or input parameters).
This is especially useful if you're performing operations on a model or similarly identifiable actions.
For example, you can just use the ID of the model you're working on.

Keep in mind that multiple jobs with the same ID will overwrite each other's progress, so this effectively disallows multiple jobs working on the same data in parallel, unless you include another factor in the ID.

```php
// Example using a reproducible ID and a Livewire component
use Mateffy\JobProgress\Contracts\HasJobProgress;
use Mateffy\JobProgress\Traits\Progress;
use App\Models\Product;

class ReproducibleIDJob implements ShouldQueue, HasJobProgress
{
    public function __construct(protected Product $product) {}
    
    public function getProgressId(): string 
    {
        return $this->product->id;
    }
}

class MyLivewire extends Component
{
    #[Locked]
    public Product $product;
    
    #[Computed]
    public function progress()
    {
        return UniqueIDJob::getProgress($this->product->id);
    }
    
    public function dispatchMyJob()
    {
        UniqueIDJob::dispatch(product: $this->product);
    }
}
```

### Using a random ID

When using a random ID, you'll most likely need to store the ID directly in the job instance itself and anywhere else you need to access it (to retrieve the job state).

```php
// Example using a random ID and a Livewire component
use Mateffy\JobProgress\Contracts\HasJobProgress;
use Mateffy\JobProgress\Traits\Progress;

class UniqueIDJob implements ShouldQueue, HasJobProgress
{
    public function __construct(protected string $id) {}
    
    public function getProgressId(): string 
    {
        return $this->id;
    }
}

class MyLivewire extends Component
{
    #[Locked]
    public string $id;
    
    #[Computed]
    public function progress()
    {
        return UniqueIDJob::getProgress($this->id);
    }
    
    public function dispatchMyJob()
    {
        // Dispatch the job
        $this->id = uniqid();
        
        UniqueIDJob::dispatch(id: $this->id);
    }
}
```

<br />

## Customizing Cache Options

The default cache key template is `job-progress:{job-class}:{id}`.
This way, IDs are automatically scoped to the job class, so you don't have to worry about accidentally using the same ID for multiple different kind of jobs.

If you want to have job IDs be unique **globally** or require similar changes,
you can override the `::getProgressConfig()` method of the `Progress` trait and customize the configuration.
See [`JobProgressConfig`](./src/JobProgressConfig.php) for all available options.

<br />

## Frequently Asked Questions

### Why would I use this package?

Sometimes you want to show progress of some kind of processing to the user. Doing it as part of a single request is risky
if the task takes longer than the request timeout, while using background jobs make it difficult to show progress or
let the user interact with the result.

### How does progress tracking work?

Each job needs to be assigned a unique ID, which can be generated or be the result of a hashing operation. 
The job class is prefixed to the ID, so the ID just needs to be unique for each job respectively.

With this unique ID, the background job will store a `JobState` object in the cache, which can then be updated by the 
job itself. This state can also be accessed from outside the job by using the same ID.

### How does job cancelling work?

Using the same state mechanism as the progress tracking, the job can also be marked as `cancelled`, which alone does
not cancel the job. Instead, you have to define "cancellation checkpoints" yourself, where a cancellation check accurs and the job can be
potentially stopped.

### Why is there a need for the `handleWithProgress` method? Can't I just keep using the `handle` method?

One difficulty with implementing background job progress is the issue of stalled / failed jobs.
If your job code throws an exception in the `handle` method, there's no way for the job status to be updated accordingly, leading to a bad UX.
The `Progress` trait of this package actually implements your `handle` method so that any exceptions thrown are caught and the job status is updated accordingly.
The same applies to job cancellation, as this is also implemented using an `Exception` class which is caught in the trait and handled appropriately.

#### Okay then, but why not use job middleware?

Great question! I'd really like to use job middleware for this, but this would unfortunately make everything a bit more fragile.
First of all, we'd have to prefill the `middleware()` method for you somehow, either inside the trait or by forcing you to extend some kind of base class.
If you now want to add your own middleware (for example for throttling etc.), you'd need to remember to re-add the progress middleware.
For this you'd have to use the awkward `use` syntax to rename and call the `Progress` trait's `middleware()` method or loose "class freedom" by needing to extend a base class.
In any case, forgetting to include the progress middleware would result in a buggy and broken system with no indication/syntax error beforehand.

I'm in the progress of writing a PR that let's job middleware also be defined in traits using `middlewareTraitName()` methods, 
similar to `bootTraitName()` or `mountTraitName()` in Eloquent/Livewire. However, I'm not sure if this is the best solution either, as middleware can be reordered, possibly leading to more undefined behaviour.

In any case, defining an abstract `handleWithProgress` method in the trait/interface and requiring you to implement it in your job class is a much stricter and safer solution for now.

### Why use the cache and not DB?

This package looks at job progress as something temporary. The cache is built to store data for a short period of time, without having to worry about cleaning up old data manually (using TTLs).

Using the cache also avoids needing migrations, so it doesn't introduce any kind of statefulness to your application or codebase itself.
It's also good practice to clear the application cache when deploying new code anyway, which avoids the issue of differing class definitions when unserializing job results. Change your result classes all you want, no invalid / old data will be left behind when using the cache (and clearing it on deployment).

One downside of using the cache is that it doesn't easily support listing all running job states, as there's no single table to query.
This was explicitly not a requirement for this package, and it's not something I'm planning to add.
However, if you really need this functionality, you could implement it yourself based on the cache backend you're using.
For example, if you're using Redis, you could use the [`SCAN` command](https://redis.io/docs/latest/commands/scan) to list all keys matching a certain pattern.

If you _really_ need the state to be stored as a DB model instead, you can have a look at [Tiger Fok's laravel-job-status package](https://github.com/imTigger/laravel-job-status), which uses a custom DB table to store job states. However, it doesn't support all of the features of this package, and doesn't look to be actively maintained.

### Is this package production ready?

This package is used in production at [immocore](https://immocore.com) to power our AI data extraction pipeline UIs.

The package is also thoroughly tested and documented. I'm planning on keeping the API stable, with changes being backwards compatible as much as possible.

<br />

## Alternatives

- [laravel-job-status](https://github.com/imTigger/laravel-job-status) by Tiger Fok

<br />

## Copyright & License

This project was created by [Lukas Mateffy](https://mateffy.me) and is maintained by [Mateffy Software Research](https://mateffy.org).

Open-Sourced using the MIT License. Please see the [License File](LICENSE.md) for more information.
