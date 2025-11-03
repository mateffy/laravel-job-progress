<?php

namespace Mateffy\JobProgress\Tests\Jobs;

use Mateffy\JobProgress\Tests\Data\News;

class SimpleJob extends TestJob
{
    public function handleWithProgress(): void
    {
        $news = app(News::class);

        $articles_to_process = $news->recent();

        $this->progress()->update(0.10); // Loading the data completes 10% of the job's progress

        $total = $articles_to_process->count();

        foreach ($articles_to_process as $index => $article) {
            $this->progress()->exitIfCancelled();

            $news->process($article);

            $this->progress()->updateWithSteps(
                completed: $index + 1,
                total: $total,
                max: 0.8,
                base: 0.1
            );
        }

        $this->progress()->complete(result: 'end_result_data');
    }
}
