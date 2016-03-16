<?php namespace App\Http\Controllers\User;
use App\Http\Controllers\User\Controller;
use App\Http\Services\FinanceService;

class FinancesController extends Controller{
    /*
     * Finances page
     * return View
     **/
    public function getIndex(FinanceService $financeService)
    {
        //dd($financeService->getUserFinanceHistory($this->user->id));
        return view('user/finances/index',[
                    'finances'       => $financeService->getFinancesByUserId($this->user->id),
                    'totalExpenses'  => $financeService->getTotalExpenses($this->user->id)
                ]);
    }

    /*
     * Finances page
     * return View
     **/
    public function postGetFinancesHistory(FinanceService $financeService)
    {
        return response()->json($financeService->getUserFinanceHistory($this->user->id));
    }

    /*
     * Create new Finance
     * 
     * @param FinanceService $financeService
     * @return Response|Redirect
     **/
    public function postAddExpense( FinanceService $financeService )
    {
       
        if( request()->ajax() )
        {
            return response()->json($financeService->addExpense($this->user,request()->all()));
        }
        return redirect('/');
    }

    /*
     * Change Finance Estimated Value
     * 
     * @param FinanceService $financeService
     **/
    public function postChangeEstimatedValue( FinanceService $financeService )
    {
       
        if( request()->ajax() )
        {
            return response()->json($financeService->changeEstimatedValue($this->user,request()->all()));
        }
        return redirect('/');
    }

    /*
     * Change Finance Estimated Value
     * 
     * @param FinanceService $financeService
     **/
    public function postUpdateBudget( FinanceService $financeService )
    {
        if( request()->ajax() )
        {
            return response()->json($financeService->updateBudget(request()->all(),$this->user->id));
        }
        return redirect('/');
    }
}