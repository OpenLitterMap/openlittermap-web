<?php

namespace App\Jobs\Emails;

use Illuminate\Support\Facades\Mail;
use App\Mail\EmailUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    public $user;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct ($user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle ()
    {
        Mail::to($this->user->email)->send(new EmailUpdate($this->user));
    }
}
