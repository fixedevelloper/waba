<?php


namespace App\Helpers;


use App\Models\Journal;
use Carbon\Carbon;
use http\Exception;
use Illuminate\Support\Facades\Http;
use Jenssegers\Agent\Agent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class Helper
{

    const STATUSSUCCESS     = 1;
    const STATUSPENDING     = 2;
    const STATUSCONFIRM        = 3;
    const STATUSREJECTED    = 4;
    const STATUSWAITING     = 5;
    const STATUSFAILD       = 6;
    const STATUSPROCESSING  = 7;
    const STATUSDELIVERYD  = 8;
    const STATUSAUTOCONFIRM  = 9;
    const TYPESANDBOX     = "SANDBOX";
    const TYPESECURE     = "SECURE";
    const METHODCHECK    = 1;
    const METHODMTN     = 2;
    const METHODOM     = 3;
    const PAIEMENTETATPARTIEL     = 1;
    const PAIEMENTETATCOMPLET     = 2;
    const OPERATIONTRANSFERT     = "TRANSFER";
    const OPERATIONTRANSFERT_CANCEL     = "TRANSFER_CANCEL";
    const OPERATIONDEPOSIT_CANCEL     = "DEPOSIT_CANCEL";
    const OPERATIONWITHDRAW_CANCEL     = "WITHDRAW_CANCEL";
    const per_page=10;
    public static function  isValidUrl($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        return true;
     /*   try {
            $response = Http::timeout(2)->post($url);
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }*/
    }

    public static function create_journal_deposit($amount,$customer_id,$old_balance){
        $journal=new Journal();
        $journal->type_operation=self::OPERATIONDEPOSIT;
        $journal->amount=$amount;
        $journal->customer_id=$customer_id;
        $journal->balance_before=$old_balance;
        $journal->balance_after=$old_balance+$amount;
        $journal->save();
    }
    public static function create_journal_Transfer($amount,$customer_id,$old_balance){
        $journal=new Journal();
        $journal->type_operation=self::OPERATIONTRANSFERT;
        $journal->amount=$amount;
        $journal->customer_id=$customer_id;
        $journal->balance_before=$old_balance;
        $journal->balance_after=$old_balance-$amount;
        $journal->save();
    }
    public static function create_journal_withdraw($amount,$customer_id,$old_balance){
        $journal=new Journal();
        $journal->type_operation=self::OPERATIONWITHDRAW;
        $journal->amount=$amount;
        $journal->customer_id=$customer_id;
        $journal->balance_before=$old_balance;
        $journal->balance_after=$old_balance-$amount;
        $journal->save();
    }
    public static function create_journal_transfer_cancel($amount,$customer_id,$old_balance){
        $journal=new Journal();
        $journal->type_operation=self::OPERATIONTRANSFERT_CANCEL;
        $journal->amount=$amount;
        $journal->customer_id=$customer_id;
        $journal->balance_before=$old_balance;
        $journal->balance_after=$old_balance+$amount;
        $journal->save();
    }
    public static function create_journal_deposit_cancel($amount,$customer_id,$old_balance){
        $journal=new Journal();
        $journal->type_operation=self::OPERATIONDEPOSIT_CANCEL;
        $journal->amount=$amount;
        $journal->customer_id=$customer_id;
        $journal->balance_before=$old_balance;
        $journal->balance_after=$old_balance;
        $journal->save();
    }
    public static function create_journal_withdraw_cancel($amount,$customer_id,$old_balance){
        $journal=new Journal();
        $journal->type_operation=self::OPERATIONWITHDRAW_CANCEL;
        $journal->amount=$amount;
        $journal->customer_id=$customer_id;
        $journal->balance_before=$old_balance;
        $journal->balance_after=$old_balance+$amount;
        $journal->save();
    }
    public static function str_slug($text){
        return strtolower(str_ireplace(" ","_",$text)) ;
    }
    public static function upload(string $dir, string $format, $image = null)
    {
        if ($image != null) {
            $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . "." . $format;
            if (!Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
            }
            Storage::disk('public')->put($dir . $imageName, file_get_contents($image));
        } else {
            $imageName = 'def.png';
        }

        return $imageName;
    }
   public static function base64Tofile($dir,$data){
       $image_base64 = base64_decode($data['image']);
       if ($data['image_type'] === 'image/png') {
          $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . '.png';
       }elseif ($data['image_type'] === 'image/jpeg'){
            $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . '.jpeg';
       } else {
           $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . '.jpg';
       }
       if (!Storage::disk('public')->exists($dir)) {
           Storage::disk('public')->makeDirectory($dir);
       }
       Storage::disk('public')->put($dir . $imageName, $image_base64);
       return $dir.'/'.$imageName;

   }
    public static function generatenumber()
    {
        $tabs=['1','2','3','4','5','6','7','8','9','0'];
        $strong=date("ymds");
        for ($i = 1; $i <= 15; $i++) {
            $strong .= $tabs[rand(0, count($tabs) - 1)];
        }
        return $strong;
    }
    public static function generateReference()
    {
        $tabs=['1','2','3','4','5','6','7','8','9','0'];
        $tab_alphpa=['q','w','e','r','t','y','u','i','o','p','a','s','d','f','g','h','j','k','l'];
        $strong=date("ds");
        for ($i = 1; $i <= 5; $i++) {
            $strong .= $tabs[rand(0, count($tabs) - 1)];
        }
        $alpha='-';
        for ($i = 1; $i <= 3; $i++) {
            $alpha .= $tab_alphpa[rand(0, count($tabs) - 1)];
        }
        return 'FRPS-'. $strong.$alpha;
    }
    public static function generatApiKey()
    {
        $tabs=['1','2','3','4','5','6','7','8','9','0','q','w','e','r','t','y','u','i','o','p','a','s','d','f','g','h','j','k','l'];
        $strong=date("ymds");
        for ($i = 1; $i <= 26; $i++) {
            $strong .= $tabs[rand(0, count($tabs) - 1)];
        }
        return $strong;
    }
    public static function generateTransactionNumber($size=10)
    {
        $tabs=['1','2','3','4','5','6','7','8','9','0','q','w','e','r','t','y','u','i','o','p','a','s','d','f','g','h','j','k','l','z','x','c','v','b','n','m'];
        $strong=date("ymds");
        for ($i = 1; $i <= $size; $i++) {
            $strong .= $tabs[rand(0, count($tabs) - 1)];
        }
        return $strong;
    }
    public static function send_creation_account($data)
    {
        $data_ = array('email' => $data['email'],'first_name' => $data['first_name'],'activate_key'=>$data['activate_key'],'slot'=>"");
        Mail::send(['html' => 'mails.verified_mail'], $data_, function ($message)
        use ($data) {
            $message->to($data['email'], $data['first_name'])->subject("Confirm email");
            $message->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
        });

    }
    public static function error_processor($validator)
    {
        $err_keeper = [];
        foreach ($validator->errors()->getMessages() as $index => $error) {
            $err_keeper[] = ['code' => $index, 'message' => $error[0]];
        }
        return $err_keeper;
    }

    public static function response_formatter($constant, $content = null, $errors = []): array
    {
        $constant = (array)$constant;
        $constant['content'] = $content;
        $constant['errors'] = $errors;
        return $constant;
    }
    public static function getAge($date){
        $le=Carbon::createFromFormat("Y-m-d",$date);
        return Carbon::now()->diffInYears($le);
    }

}
