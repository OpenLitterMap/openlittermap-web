<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExportWithLink extends Mailable
{
    use Queueable, SerializesModels;

    public $email, $path;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct ($email, $path)
    {
        $this->email = $email;
        $this->path = $path;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('info@openlittermap.com')
            ->subject('OpenLitterMap Data')
            ->view('emails.downloads.opendata_link')
            ->with([
                'url' => "http://s3.olm.aws.com/" . $this->path, // filepath
            ]);
    }
}
