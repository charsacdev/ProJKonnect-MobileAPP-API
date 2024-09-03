<?php

namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Kutia\Larafirebase\Messages\FirebaseMessage;

class SendPushNotification extends Notification
{
    use Queueable;

    protected $title;
    protected $message;
    protected $fcmTokens;

    public function __construct($title,$message,$fcmTokens)
    {
        $this->title = $title;
        $this->message = $message;
        $this->fcmTokens = $fcmTokens;
    }

    public function via($notifiable)
    {
        return ['firebase'];
    }

    
    public function toFirebase($notifiable)
    {
        $deviceTokens = [
            $this->fcmTokens
        ];

        return (new FirebaseMessage)
            ->withTitle($this->title)
            ->withBody($this->message)
            #->withImage('https://projectkonnect.com/assets/frontend/images/fav.png')
            ->withIcon('https://projectkonnect.com/assets/frontend/images/fav.png')
            ->withSound('default')
            ->withPriority('high')->asNotification($deviceTokens);
    }
   

    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
