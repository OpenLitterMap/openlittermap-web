<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExportFailed extends Mailable
{
    use Queueable, SerializesModels;

    public function build()
    {
        return $this->from('info@openlittermap.com')
            ->subject('OpenLitterMap Data Export — Failed')
            ->view('emails.downloads.export_failed');
    }
}
