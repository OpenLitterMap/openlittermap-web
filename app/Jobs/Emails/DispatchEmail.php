<?php

namespace App\Jobs\Emails;

use App\Mail\EmailUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class DispatchEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function handle(): void
    {
        Mail::to($this->user->email)->send(new EmailUpdate($this->user));
    }
}
