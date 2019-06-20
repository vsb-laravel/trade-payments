<?php
namespace Vsb\Events;

use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;


class DepositEvent //implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */

    public $payment;

    public function __construct($payment)
    {
        $this->payment = $payment;
    }

    public function  broadcastAs()
    {
        return 'private_channel';
    }

    public  function broadcastWith()
    {
        return ['private_data' => "", 'user_id' => 1, 'type' => 'private'];
        $user = new User();

        $request = $user->load(['accounts'=>function($query) { $query->with(['currency']);},
                                'messages',
                             'transactions'=>function($query) { $query->with(['invoice','withdrawal','merchant']); },
                             'documents', 'deal'=>function($query){ return $query->with(['instrument','account']);},
                             'meta'])
                    ->where('id', '=', Auth::id())
                    ->first()
                    ->toArray();

        return ['private_data' => $request, 'user_id' => Auth::id(), 'type' => 'private', 'text' => '2221'];
    }



    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-private');
    }
}
