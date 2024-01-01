<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ExportWithLink extends Mailable
{
    use Queueable;
    use SerializesModels;
    public $path;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct ($path)
    {
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
                'url' => Storage::disk('s3')->url($this->path)
            ]);
    }
}
