<?php

namespace App\Notifications;

use App\Models\FreightPost;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\Telegram\TelegramMessage;

class FreightChannelAnnouncement extends Notification
{
    public function __construct(public FreightPost $post) {}

    public function via(object $notifiable): array
    {
        return ['telegram'];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $this->post->loadMissing(['originLocation', 'destinationLocation', 'cargoType', 'truckType']);

        $origin = $this->post->originLocation?->name ?? 'Not specified';
        $destination = $this->post->destinationLocation?->name ?? 'Not specified';
        $cargo = $this->post->item_name ?: ($this->post->cargoType?->name ?? 'Freight');
        $pickup = $this->post->pickup_date?->format('M j, Y') ?? 'To be arranged';
        $truck = $this->post->truckType?->name ?? 'Not specified';

        $text = implode("\n", [
            '🚚 New approved freight on Numan Freight',
            '',
            '📍 '.Str::limit($origin, 80).' → '.Str::limit($destination, 80),
            '📦 Cargo: '.Str::limit($cargo, 100),
            '🚛 Truck: '.Str::limit($truck, 80),
            '📅 Pickup: '.$pickup,
            '🔖 Reference: '.$this->post->ulid,
        ]);

        return TelegramMessage::create()
            ->normal()
            ->content($text)
            ->button('View freight', route('freight.public.show', ['ulid' => $this->post->ulid]))
            ->button('Get private alerts', route('notifications.show'));
    }
}
