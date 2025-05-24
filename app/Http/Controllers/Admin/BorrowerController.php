<?php

namespace App\Http\Controllers\Admin;

use App\Constants\ConstUserStatus;
use App\Http\Controllers\Controller;
use App\Models\Borrower;
use App\Models\User;
use App\Traits\BaseApiResponse;
use Illuminate\Http\Request;

class BorrowerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    use BaseApiResponse;
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', env('PAGINATION_PER_PAGE', 10));
        $search = $request->query('search');

        $borrowerQuery = Borrower::query()
            ->with(['user'])
            ->whereHas('user', function ($query) use ($search) {
                if ($search) {
                    $query->where('phone', 'like', '%' . $search . '%');
                }
            });

        $borrowers = $borrowerQuery->paginate($perPage);

        // Count active and inactive borrowers (without search filter)
        $activeCount = Borrower::whereHas('user', function ($query) {
            $query->where('status', 1);
        })->count();

        $inactiveCount = Borrower::whereHas('user', function ($query) {
            $query->where('status', 0);
        })->count();

        $totalCount = Borrower::count();

        return $this->success([
            'borrowers' => $borrowers,
            'active_count' => $activeCount,
            'inactive_count' => $inactiveCount,
            'total_count' => $totalCount,
        ]);
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //show borrower details
        $borrower = Borrower::find($id);
        if ($borrower) {
            return $this->success($borrower);
        }
        return $this->failed('Borrower not found', 404);
    }

   //update status
    public function borrowerStatus(Request $request, string $id)
    {
        //validate status
        $request->validate([
            'status' => 'required',
        ]);

        $borrower = Borrower::find($id);
        if ($borrower) {
           $borrowerUser = User::query()->where('id', $borrower->user_id)->first();
           $borrowerUser->status = $request->status;
           $borrowerUser->save(); //save the updated status
            return $this->success($borrowerUser);
        }
        return $this->failed('Borrower not found', 404);
    }

}
