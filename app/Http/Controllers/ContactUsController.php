<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactMail;
use Illuminate\Support\Facades\Mail;

class ContactUsController extends Controller
{

    public function __invoke(ContactRequest $request)
    {
        Mail::to('info@openlittermap.com')
            ->send(new ContactMail(
                $request->subject,
                $request->message,
                $request->name,
                $request->email
            ));
    }
}
