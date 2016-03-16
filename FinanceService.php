<?php namespace App\Http\Services;
use App\Models\Finance;
use App\Models\FinanceHistory;
use App\User;
use Validator,DB;

class FinanceService
{
    protected $finance,$user,$financeHistory;
    /**
     * Instantiate a new User instance.
     */
    public function __construct( Finance $finance, User $user,FinanceHistory $financeHistory )
    {
       $this->finance = $finance;
       $this->user = $user;
       $this->financeHistory = $financeHistory;
    }

    /*
     * Get user finances
     *
     * @param Int $user_id
     * @return Array
     */
    public function getFinancesByUserId( $user_id )
    {
        return $this->finance->where('user_id',$user_id)->orderBy("id","DESC")->get();
    }
/*
 * Get last Expenses by limit
 */
    public function getUserLastExpanse($user){
        return $this->finance
                ->where('user_id',$user->id)
                ->orderBy("id","DESC")
                ->limit(1)
                ->first();
    }
    /*
     * Add New Finance 
     * 
     * @param Int $user_id
     * @param Array $data
     * @return Array|Boolean
     */
    public function addExpense( $user,$data )
    {

        $validator = Validator::make(
                $data,
                [
                    'espense'         =>'required',
                    'estimated_value' =>'required|not_in:0|numeric'
                ],
                [
                    'not_in'=>'The estimated value can not be zero',
                    'estimated_value.required'=>"There is not estimated value, and you can't add new expense",
                    'espense.required'=>"The expense field is required."
                ]
        );

        if($validator->fails())
            return ['success'=>false,'message'=>$validator->errors()];
        
        $totalExpenses = $this->getTotalExpenses($user->id);
        
        if(($difference = $totalExpenses+$data['estimated_value']) > $user->budget)
        {
            return ['success'=>false,'message'=>['Your budget is not enough &pound;'.  abs($difference)]];
        }
        $data['user_id'] = $user->id;
        if(!$finance = $this->finance->create($data))
        {
            return ['success'=>false,'message'=>["Cant create finance."]];
        }

        $finance["created"] = \Carbon\Carbon::now()->subMinutes((strtotime(date("Y-m-d H:i:s"))-strtotime($finance["created_at"]))/60)->diffForHumans();
        $finance["formated_budget"] = number_format($finance["budget"], 2);
        $finance["formated_estimated_value"] = number_format($finance["estimated_value"], 2);
        
        $this->createUserFinanceHistory($user);
        return ['success'=>true,
            'message' => ['successfuly created'],
            'total_expenses'=>number_format($this->getTotalExpenses($user->id),2),
            'remaining_budget'=>number_format($user->budget - $this->getTotalExpenses($user->id),2),
            'finance'=>$finance
        ];
    }

    /*
     * Change Finance Estimated Value
     * 
     * @param Int $user_id
     * @param Array $data
     * @return Array|Boolean
     */
    public function changeEstimatedValue( $user,$data )
    {        
        if($user_finance = $this->finance->find($data["finance_id"]))
        {
            if ($user->id==$user_finance->user_id)
            {
                $user_finance->update(['estimated_value'=> $data["estimated_value"]]);
                $user_finance->new_estimated_value = number_format($user_finance->estimated_value , 2);
                $total_expenses = $this->getTotalExpenses($user->id);
                $this->createUserFinanceHistory($user);
                return [
                    'success'=>true,
                    'message' => ['Successfuly updated'],
                    'finance'=>$user_finance,
                    'total_expenses'=>number_format($total_expenses,2),
                    'remaining_budget'=>number_format($user->budget-$total_expenses,2)
                ];
            }
            return ['success'=>false,'message' => ['Not your finance']];
        }
        return ['success'=>false,'message' => ['cant find']];
    }
    
    /*
     * Update budget
     * 
     * @param Array $request
     * @param Array $user_id
     * @return Boolean
     */
    public function updateBudget( $request, $user_id )
    {
        $validator = Validator::make(
            $request,
            [
                'budget'=>'required|not_in:0|numeric'
            ],
            [
                'not_in'=>'The budget can not be zero'
            ]
        );

        if($validator->fails()){
            return ['success'=>false,'message'=>$validator->messages()];
        }
        if($user = $this->user->find($user_id)->update(['budget'=>$request['budget']]))
        {
            $this->createUserFinanceHistory($this->user->find($user_id));
            return ['success'=>true,'user'=>$user];
        }
        return ['success'=>false,'message'=>'Cant update budget'];
    }

    /*
     * Get Total Expenses
     * 
     * @param Int $user_id
     * @return Int
     */
    public function getTotalExpenses($user_id)
    {
        return $this->finance->where(["user_id"=>$user_id])->sum("estimated_value");
    }
    /*
     * Create finance history
     * 
     * @param Array $data
     * @param Int $user_id
     * @return FinanceHistory|Boolean
     */
    public function createUserFinanceHistory( $user )
    {
        $total_expenses = $this->finance->where('user_id',$user->id)->sum('estimated_value');
        $this->financeHistory->create(['user_id'=>$user->id,'budget'=>$user->budget,'projected_expenses'=>$user->budget-$total_expenses,'actual_expenses'=>$total_expenses]);
    }

    /*
     * get finance history
     * 
     * @param Int $user_id
     * @return All FinanceHistory
     */
    public function getUserFinanceHistory( $user_id )
    {
        $chartData = [];
        $histories = $this->financeHistory
                ->where('user_id',$user_id)
                ->whereBetween('created_at',[date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')])
                ->orderBy('created_at','DESC')
                ->limit(1)
                ->get();
        
        foreach($histories as $history){
            //dd(strtotime($history->created_at));
            $chartData['labels'][]   = date("M d \t\h",strtotime($history->created_at));
            $chartData['budget'][] = $history->budget;
            $chartData['projected_expenses'][] = $history->projected_expenses;
            $chartData['actual_expenses'][] = $history->actual_expenses;
            
        }
        return $chartData;
    }
}