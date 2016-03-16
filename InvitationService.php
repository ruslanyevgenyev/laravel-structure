<?php namespace App\Http\Services;

use App\Models\Invitation;
use App\Models\Team;
use Auth, Mail,Validator;

class InvitationService
{
    protected $authUser,$team;

    /**
     * Instantiate a new Invitation instance.
     */
    public function __construct( Invitation $invitation, Team $team ) {
        $this->invitation = $invitation;
        $this->team = $team;
        $this->authUser = Auth::user()->user();
    }

    /*
     * Check if such user's email is olready exists
     * 
     * @param $email String
     * @return Array|Boolean
     */
    public function invitedUserExists($data)
    {
        if(isset($data['email']))
            $result = $this->invitation->where('email',$data['email'])->first();
        if(isset($data['phone']))
            $result = $this->invitation->where('phone',$data['phone'])->first();
        return $result;
    }

    /*
     * Delete invited user by user id into `invitations` table
     * 
     * @param $id Int
     * @return Boolean
     */
    public function deleteIntvitedUserByEmail($user)
    {
        $invitedUser = $this->invitation->where('email',$user['email']);
        $this->team->create(['user_id'=>$user['user_id'],'invited_id'=>$user['invited_id']]);
        return $invitedUser->delete();
    }

    /*
     * Check if invited users hash is already exists
     * 
     * @param $hash String
     * @return Array|Boolean
     */
    public function hashExists( $hash )
    {
        return $this->invitation->where('hash',$hash)->first();
    }

    /*
     * Send invitation to user
     * 
     * @param $user Array
     * @return Array|Boolean
     */
    public function inviteUser($user)
    {
        if(isset($user['spouse_email']))
            $result = $this->inviteUserByEmail($user);
        if(isset($user['email']))
            $result = $this->inviteUserByEmail($user);
        if(isset($user['phone']))
            $result = $this->inviteUserByPhone($user);
        return $result;
    }
    /*
     * Send invitation to user on email
     * 
     * @param $user Array
     * @return Array|Boolean
     */
    public function inviteUserByEmail($user)
    {
        if(isset($user['spouse_email'])){
            $condition = ['spouse_email'=>'email'];
            $user['email'] = $user['spouse_email'];
        }
        else
            $condition = ['email'=>'required|email|unique:users'];
        $validator = Validator::make(
                    $user,
                    $condition
                );
        if ($validator->fails())
        {
            return ['success'=>false,'message'=>$validator->messages()];
        }

        $data = $this->getDataForInviteByEmail();
        $data['user'] = $user;
        Mail::send('emails.user_invitation', ['data' => $data], function ($message) use ($user) {
            $message->from('unchained@unchained.com', url());
            $message->to($user['email'], "Test")->subject('Your Reminder!');
        });
        if($this->invitedUserExists(['email'=>$user['email']]))
            return ['success'=>false, 'message'=>['We have sent an invitation to the user']];
        if(!$this->invitedUserExists(['email'=>$user['email']]))
            $invited_user = $this->invitation->create(['email'=>$user['email'],'hash'=>$data['hash'],'user_id'=>$this->authUser->id,'type'=>(isset($user['type'])?$user['type']:'')]);
        return ['success'=>true,'invited_user'=>$invited_user,'message'=>['We have sent an invitation to the user']];
    }
    /*
     * Send invitation to user on phone
     * 
     * @param $user Array
     * @return Array|Boolean
     */
    public function inviteUserByPhone($user)
    {
        $messages = array(
            'phone.min:11' => 'The phone number must contain at least 11 characters',
        );
        $validator = Validator::make(
            $user,
            ['phone'=>'required|min:11'],
            $messages
        );
        if ($validator->fails())
            return ['success'=>false,'message'=>$validator->messages()];
        if($this->invitedUserExists(['phone'=>$user['phone']]))
            return ['success'=>false,'message'=>['This user was already invited']];
        $phone = $this->getDataForInviteByPhone();
        if($user['phone'][0] == "0")
            $user['phone'] = substr($user['phone'], 1);

        $to = ($user['phone'][0] == "+44"?$user['phone']:'+44'.$user['phone']);
        $twilio_configs = \Config::get('twilio.twilio.connections.twilio');
        $twilio_configs['to'] = request()->get('');
        $twilio = new \Aloha\Twilio\Twilio($twilio_configs['sid'], $twilio_configs['token'], $twilio_configs['from']);
        try {
            $result = $twilio->message($to, $phone['message']);
            $invited_user = $this->invitation->create(['phone'=>$to,'hash'=>$phone['hash'],'user_id'=>$this->authUser->id]);
            return ['success'=>true,'message'=>['Invitation on '.$to.' successfuly sended'],'phone'=>$to, 'invited_user'=>$invited_user];

        } catch ( \Services_Twilio_RestException $e ) {
            return ['success'=>false,'message'=>['The " '.$user['phone'].' " number is incorrect.  Please enter in the normal UK format.  XXXXX XXXXXX']];
        }
        $invited_user = $this->invitation->create(['phone'=>$user['phone'],'hash'=>$phone['hash'],'user_id'=>$this->authUser->id]);
    }

    /*
     * Get Message And Hash For Invite By Email
     *
     * @param $user Array
     * @return Array
     */
    public function getDataForInviteByEmail()
    {
        $hash = md5(uniqid());
        $action = url('invitations/invited-user',[$hash]);

        return [
                    'hash'=>$hash,
                    'action'=>$action
                ];
    }

    /*
     * Get Message And Hash For Invite By Phone
     *
     * @return Array
     */
    public function getDataForInviteByPhone()
    {
        $hash = uniqid();
        $message = "Please, go by this link ".url('invitations/invited-user',[$hash])." and get registered.";
        return [
                    'hash'=>$hash,
                    'message'=>$message
                ];
    }
    
    
    /*
     * Get invited peoples
     * 
     * @param Int $user_id
     * @return Array|Boolean
     */
    public function getInvitedPeoples( $user_id )
    {
        return $this->invitation->where('user_id',$user_id)->get();
    }
}