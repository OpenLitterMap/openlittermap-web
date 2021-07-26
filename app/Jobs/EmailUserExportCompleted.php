<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Mail\ExportWithLink;
use Illuminate\Support\Facades\Mail;

class EmailUserExportCompleted implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $email, $path;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct ($email, $path)
    {
        $this->email = $email;
        $this->path = $path;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Mail::to($this->email)->send(new ExportWithLink($this->email, $this->path));
    }
}
