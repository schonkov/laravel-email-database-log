<?php
namespace Dmcbrn\LaravelEmailDatabaseLog;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Storage;
use Dmcbrn\LaravelEmailDatabaseLog\LaravelEvents\EmailLogged;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;

class EmailLogger
{
    /**
     * Handle the event.
     *
     * @param MessageSending $event
     */
    public function handle(MessageSending $event)
    {
        $message = $event->message;

        //was
        // $messageId = strtok($message->getId(), '@');

        // Safely get the Message-ID header
        $messageIdHeader = $message->getHeaders()->get('Message-ID');
        $messageId = $messageIdHeader ? strtok($messageIdHeader->getBodyAsString(), '@') : uniqid();


        //was
        // $attachments = [];
        // foreach ($message->getChildren() as $child) {
        //     //docs for this below: http://phpdox.de/demo/Symfony2/classes/Swift_Mime_SimpleMimeEntity/getChildren.xhtml
        //     if(in_array(get_class($child),['Swift_EmbeddedFile','Swift_Attachment'])) {
        //         $attachmentPath = $messageId . '/' . $child->getFilename();
        //         Storage::disk(config('email_log.disk'))->put($attachmentPath, $child->getBody());
        //         $attachments[] = $attachmentPath;
        //     }
        // }


        $attachments = $this->handleAttachments($message, $messageId);


        // Get the body content
        // $bodyContent = $this->getBodyAsString($message->getBody());

        $bodyContent = $message->getBody()->bodyToString();

        $array = [
            'date' => now(),
            'from' => $this->formatAddressField($message, 'From'),
            'to' => $this->formatAddressField($message, 'To'),
            'cc' => $this->formatAddressField($message, 'Cc'),
            'bcc' => $this->formatAddressField($message, 'Bcc'),
            'subject' => $message->getSubject(),
            // 'body' => $bodyContent ?: '(No Content)',
            // 'body' => $message->getBody()->bodyToString(), //->bodyToString(),
            'body' => $this->decodeQuotedPrintable($bodyContent),
            'headers' => $this->decodeQuotedPrintable($message->getHeaders()->toString()),
            'attachments' => $attachments,
            'messageId' => $messageId,
            'mail_driver' => config('mail.driver'),
        ];
        // dd($array);
        // Log email to database
        $emailLog = EmailLog::create($array);

        event(new EmailLogged($emailLog));
    }

    private function decodeQuotedPrintable($input)
    {
        return quoted_printable_decode($input);
    }

    private function handleAttachments($message, $messageId)
    {
        $attachments = [];
        
        foreach ($message->getAttachments() as $attachment) {
            // Determine the attachment's file name
            $filename = $attachment->getFilename();
            
            // Create the path where the attachment will be stored
            $attachmentPath = $messageId . '/' . $filename;
            
            // Save the attachment to the configured storage disk
            Storage::disk(config('email_log.disk'))->put($attachmentPath, $attachment->getBody());
            
            // Store the path in the attachments array
            $attachments[] = $attachmentPath;
        }
        
        // Return the attachments array as a JSON string or other format depending on your database schema
        return empty($attachments) ? null : implode(', ', $attachments);
    }

    /**
     * Get the body of the email as a string.
     *
     * @param AbstractPart|null $body
     * @return string|null
     */
    private function getBodyAsString(?AbstractPart $body): ?string
    {
        if ($body === null) {
            return null;
        }

        // Handle multipart/alternative
        if ($body instanceof AlternativePart) {
            foreach ($body->getParts() as $part) {
                if ($part instanceof TextPart && $part->getMediaType() === 'text/html') {
                    return $part->getBody();
                }
            }
            foreach ($body->getParts() as $part) {
                if ($part instanceof TextPart && $part->getMediaType() === 'text/plain') {
                    return $part->getBody();
                }
            }
        }

        // Handle plain text or HTML body
        if ($body instanceof TextPart) {
            return $body->getBody();
        }

        // If it reaches here, no valid content was found
        return '(No Content)';
    }


    /**
     * Format address strings for sender, to, cc, bcc.
     *
     * @param Email $message
     * @param string $field
     * @return null|string
     */
    private function formatAddressField(Email $message, $field)
    {
        $header = $message->getHeaders()->get($field);

        if (!$header) {
            return null;
        }

        $mailboxes = $header->getBodyAsString();

        return $mailboxes;
    }

    //old
    // function formatAddressField($message, $field)
    // {
    //     $headers = $message->getHeaders();

    //     if (!$headers->has($field)) {
    //         return null;
    //     }

    //     $mailboxes = $headers->get($field); //->getFieldBodyModel();

    //     $strings = [];
    //     foreach ($mailboxes as $email => $name) {
    //         $mailboxStr = $email;
    //         if (null !== $name) {
    //             $mailboxStr = $name . ' <' . $mailboxStr . '>';
    //         }
    //         $strings[] = $mailboxStr;
    //     }
    //     return implode(', ', $strings);
    // }
}
/*
<?php
//old code
namespace Dmcbrn\LaravelEmailDatabaseLog;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Storage;
use Dmcbrn\LaravelEmailDatabaseLog\LaravelEvents\EmailLogged;

class EmailLogger
{
    public function handle(MessageSending $event)
    {
        $message = $event->message;

        $messageId = strtok($message->getId(), '@');

        $attachments = [];
        foreach ($message->getChildren() as $child) {
            //docs for this below: http://phpdox.de/demo/Symfony2/classes/Swift_Mime_SimpleMimeEntity/getChildren.xhtml
            if(in_array(get_class($child),['Swift_EmbeddedFile','Swift_Attachment'])) {
                $attachmentPath = $messageId . '/' . $child->getFilename();
                Storage::disk(config('email_log.disk'))->put($attachmentPath, $child->getBody());
                $attachments[] = $attachmentPath;
            }
        }

        $emailLog = EmailLog::create([
            'date' => date('Y-m-d H:i:s'),
            'from' => $this->formatAddressField($message, 'From'),
            'to' => $this->formatAddressField($message, 'To'),
            'cc' => $this->formatAddressField($message, 'Cc'),
            'bcc' => $this->formatAddressField($message, 'Bcc'),
            'subject' => $message->getSubject(),
            'body' => $message->getBody(),
            'headers' => (string)$message->getHeaders(),
            'attachments' => empty($attachments) ? null : implode(', ', $attachments),
            'messageId' => $messageId,
            'mail_driver' => config('mail.driver'),
        ]);

        event(new EmailLogged($emailLog));
    }

     //Format address strings for sender, to, cc, bcc.
     
     // @param $message
    // @param $field
    // @return null|string
    
    function formatAddressField($message, $field)
    {
        $headers = $message->getHeaders();

        if (!$headers->has($field)) {
            return null;
        }

        $mailboxes = $headers->get($field)->getFieldBodyModel();

        $strings = [];
        foreach ($mailboxes as $email => $name) {
            $mailboxStr = $email;
            if (null !== $name) {
                $mailboxStr = $name . ' <' . $mailboxStr . '>';
            }
            $strings[] = $mailboxStr;
        }
        return implode(', ', $strings);
    }
}
/*/
