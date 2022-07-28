<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MailMaestroPMSLog extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $mailPMS;

    public function __construct($mailPMS)
    {
        $this->mailPMS = $mailPMS;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->to($this->mailPMS->emailAddress)
            ->from('integrations@api.mynuvola.net', 'Nuvola integrations')
            ->view('emails.EmailMaestroPMSLog')
            ->text('emails.EmailMaestroPMSLog_Plain')
            ->subject('Maestro PMS Logs Report')
            ->with(
                [
                    'QuantityLogs' => $this->mailPMS->QuantityLogs,
                ]
            );
    }
}
