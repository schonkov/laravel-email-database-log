<?php

namespace Dmcbrn\LaravelEmailDatabaseLog;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Dmcbrn\LaravelEmailDatabaseLog\Events\EventFactory;
use Illuminate\Support\Facades\File;

class EmailLogController extends Controller {

    public function index(Request $request)
    {
        //get list of emails
        list($emails, $filterEmail, $filterSubject) = $this->getEmailsForListing($request);

        //return
        return view('email-logger::index', compact('emails','filterEmail','filterSubject'));
    }

    public function indexApi(Request $request)
    {
        //get list of emails
        list($emails, $filterEmail, $filterSubject) = $this->getEmailsForListing($request);

        //return
        return response()->json($emails, 200);
    }

    public function show(int $id)
    {
        //get email
        $email = $this->getEmail($id, false);

        //return
        return view('email-logger::show', compact('email'));
    }

    public function showApi(int $id)
    {
        //get email
        $email = $this->getEmail($id, true);

        //return
        return response()->json(compact('email'), 200);
    }

    public function fetchAttachment(int $id, int $attachment)
    {
        //get email and attachments' paths
        $email = EmailLog::select('id','attachments')->find($id);
        $attachmentFullPath = explode(', ',$email->attachments)[$attachment];

        //get file and mime type
        $disk = Storage::disk(config('email_log.disk'));
        $file = $disk->get(urldecode($attachmentFullPath));
        $mimeType = $disk->mimeType(urldecode($attachmentFullPath));

        //return file
        return response($file, 200)->header('Content-Type', $mimeType);
    }

    public function createEvent(Request $request)
    {
    	$event = EventFactory::create('mailgun');

    	//check if event is valid
    	if(!$event)
            return response('Error: Unsupported Service', 400)
                ->header('Content-Type', 'text/plain');

        //validate the $request data for this $event
        if(!$event->verify($request))
            return response('Error: verification failed', 400)
                ->header('Content-Type', 'text/plain');

        //save event
        return $event->saveEvent($request);
    }
    
    public function deleteOldEmails(Request $request)
    {
        //delete old emails
        $message = $this->deleteEmailsBeforeDate($request);

        //return
        return redirect(route('email-log'))
            ->with('status', $message);
    }
    
    public function deleteOldEmailsApi(Request $request)
    {
        //delete old emails
        $message = $this->deleteEmailsBeforeDate($request);

        //return
        return response()->json(compact('message'), 200);
    }

    private function getEmail(int $id, bool $isApi)
    {
        //get email
        $email = EmailLog::with('events')
            ->find($id);

        // Ensure the body is correctly handled
        if ($email && is_string($email->body)) {
            // Decode the quoted-printable body if necessary
            $email->body = quoted_printable_decode($email->body);
        }

        //format attachments as collection
        $attachments = collect();

        //was
        // //check if there are any attachments
        // $attachmentsArray = array_filter(explode(', ',$email->attachments));
        // if(count($attachmentsArray) > 0) {
        //     //set up new $email->attachments values
        //     foreach($attachmentsArray as $key => $attachment) {
        //         //update each attachment's value depending if file can be found on disk
        //         $fileName = basename($attachment);
        //         if(Storage::disk(config('email_log.disk'))->exists($attachment)) {
        //             $route = $isApi 
        //                 ? 'api.email-log.fetch-attachment'
        //                 : 'email-log.fetch-attachment';
        //             $formattedAttachment = [
        //                 'name' => $fileName,
        //                 'route' => route($route, [
        //                     'id' => $email->id,
        //                     'attachment' => $key,
        //                 ]),
        //             ];
        //         } else {
        //             $formattedAttachment = [
        //                 'name' => $fileName,
        //                 'message' => 'file not found',
        //             ];
        //         }
        //         $attachments->push($formattedAttachment);
        //     }
        // }

        // Handle attachments only if they exist
        if (!empty($email->attachments)) {
            $attachmentsArray = array_filter(explode(', ', $email->attachments));

            // dd($attachmentsArray);
            ///home/ubuntu/epodrome-multibranches/dev/storage/app/email_log_attachments/66cca1aa00f56/commande_details_1330_lundi 26 août 2024 15h39m.pdf
            foreach ($attachmentsArray as $key => $attachment) {

                $file_dir = storage_path('app/email_log_attachments/').$attachment;
                // dd($file_dir, File::exists($file_dir), );
                
                
                // Validate that the attachment is a valid path and exists on the disk
                if ($this->isValidPath($attachment) && File::exists($file_dir)) {
                    $route = $isApi
                        ? 'api.email-log.fetch-attachment'
                        : 'email-log.fetch-attachment';

                    $attachments->push([
                        'name' => basename($attachment),
                        'route' => route($route, [
                            'id' => $email->id,
                            'attachment' => $key,
                        ]),
                    ]);
                } else {
                    $attachments->push([
                        'name' => basename($attachment),
                        'message' => 'file not found',
                    ]);
                }
            }
        }

        //update the attachments
        $email->attachments = $attachments;

        //return
        return $email;
    }

    private function isValidPath(string $path): bool
    {
        // Implement logic to validate if the string is a valid path
        return strpos($path, '/') !== false;
    }

    private function getEmailsForListing(Request $request)
    {
        //validate
        $request->validate([
            'filterEmail' => 'string',
            'filterSubject' => 'string',
            'per_page' => 'numeric',
        ]);

        //get emails
        $filterEmail = $request->filterEmail;
        $filterSubject = $request->filterSubject;
        $emails = EmailLog::with([
                'events' => function($q) {
                    $q->select('messageId','created_at','event');
                }
            ])
            ->select('id','messageId','date','from','to','subject')
            ->when($filterEmail, function($q) use($filterEmail) {
                return $q->where('to','like','%'.$filterEmail.'%');
            })
            ->when($filterSubject, function($q) use($filterSubject) {
                return $q->where('subject','like','%'.$filterSubject.'%');
            })
            ->orderBy('id','desc')
            ->paginate($request->per_page ?: 20);

        return [$emails, $filterEmail, $filterSubject];
    }

    private function deleteEmailsBeforeDate(Request $request)
    {
        //validate
        $request->validate([
            'date' => 'required|date',
        ]);

        //get emails
        $date = strtotime($request->date);
        $emails = EmailLog::select('id', 'date', 'messageId')
            ->where('date', '<=', date("c", $date))
            ->get();

        //delete email attachments
        foreach ($emails as $email) {
            Storage::disk(config('email_log.disk'))
                ->deleteDirectory($email->messageId);
        }
        
        //delete emails
        $numberOfDeletedEmails = EmailLog::destroy($emails->pluck('id'));

        //return message
        return 'Deleted ' . $deleted . ' emails logged on and before ' . date("r", $date);
    }
}
