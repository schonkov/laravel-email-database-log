<!DOCTYPE html>
<html>
    <head>
        <title>Emails Log</title>
    </head>

    <!-- Tailwind CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <body style="background: white;">
        <a class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-gray-500 bg-white border border-gray-300 cursor-default leading-5 dark:bg-gray-800 dark:border-gray-600" href="{{ route('email-log') }}">Back to All</a>
        <h1>Email:</h1>

        <ul>
            <li>{{ $email->date }}</li>
            <li>From: {{ $email->from }}</li>
            <li>To: {{ $email->to }}</li>
            <li>Subject: {{ $email->subject }}</li>
            <li>Body: <br>
                <div>{!! $email->body !!}</div>
            </li>
            <li>Attachments:
                @if(count($email->attachments))
                    <ul>
                        @foreach($email->attachments as $attachment)
                            <li>
                                @if(array_key_exists('route', $attachment))
                                    <a  class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-gray-500 bg-white border border-gray-300 cursor-default leading-5 dark:bg-gray-800 dark:border-gray-600" href="{{ $attachment['route'] }}">{{ $attachment['name'] }}</a>
                                @else
                                    {{ $attachment['name'] }} - {{ $attachment['message'] }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    NONE
                @endif
            </li>
            <li>Headers: {{ $email->headers }}</li>
            <li>Message ID: {{ $email->messageId }}</li>
            <li>Mail Driver: {{ $email->mail_driver }}</li>
            <li>Events:
                @if(count($email->events ?? []) > 0)
                    <ul>
                        @foreach($email->events as $event)
                            <li><strong>{{ $event->event }}</strong> {{ $event->created_at }}</li>
                        @endforeach
                    </ul>
                @endif
            </li>
        </ul>

        <a class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-gray-500 bg-white border border-gray-300 cursor-default leading-5 dark:bg-gray-800 dark:border-gray-600" href="{{ route('email-log') }}">Back to All</a>
    </body>
</html>
