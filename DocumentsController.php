<?php namespace App\Http\Controllers\User;

use App\Http\Controllers\User\Controller;
use App\Http\Services\DocumentService;
use File;

class DocumentsController extends Controller{

    /*
     * User dashboard page
     * 
     * @return View
     **/
    public function getIndex(DocumentService $documentService)
    {
        return view('user/documents/index',['documents'=>$documentService->getUserDocumentGroupedTypes($this->user->id)]);
    }

    /*
     * Upload the document
     * 
     * @param DocumentService $documentService
     * @return Response|Redirect
     */
    public function postStore(DocumentService $documentService)
    {
        if(request()->ajax()){
            if(request()->hasFile('file'))
                return response()->json($documentService->uploadDocuments(request()->all(),$this->user));
        }
        return redirect('/');
    }

    /*
     * Download the document
     * 
     * @param DocumentService $documentService
     * @return Download
     */
    public function getDownload( DocumentService $documentService,$name,$title )
    {
        $file = public_path().'/uploads/users/documents/'.$name;
        
        if(File::exists($file))
             return response()->download($file,$title);
        return redirect()->back()->withErrors('File not found');
    }

    /*
     * Share with users
     * 
     * @param DocumentService $documentService
     * @return Response
     */
    public function postShare( DocumentService $documentService )
    {
        return response()->json($documentService->shareWith(request()->all(),$this->user));
    }

    /*
     * Share with users
     * 
     * @param DocumentService $documentService
     * @return Response
     */
    public function postGetHistory( DocumentService $documentService )
    {
        return response()->json($documentService->getHistory(request()->all(),$this->user));
    }

    /*
     * Share with users
     * 
     * @param DocumentService $documentService
     * @return Response
     */
    public function deleteSharedWithUser( DocumentService $documentService, $user_id )
    {
        return response()->json($documentService->deleteSharedWhithUserHistory($user_id));
    }

    /*
     * Share with users
     * 
     * @param DocumentService $documentService
     * @return Response
     */
    public function postUpdate( DocumentService $documentService, $id )
    {
        if(request()->ajax())
            return response()->json($documentService->updateDocumentById(request()->file('file'), $id, $this->user->id));
        return redirect()->back()->withErrors('Access denied');
    }

    /*
     * Share with users
     * 
     * @param DocumentService $documentService
     * @return Response
     */
    public function postGetSahredUsers( DocumentService $documentService )
    {
        $share_with_items = $documentService->getSharedUsers(request()->all(),$this->user->id);
        if(request()->ajax())
            return response()->json($share_with_items);
        return redirect()->back()->withErrors('Access denied');
    }
    

    /*
     * Share with users
     * 
     * @param DocumentService $documentService
     * @return Response
     */
    public function putStoreType( DocumentService $documentService )
    {
        if(request()->ajax())
            return response()->json($documentService->createNewDocumentType(request()->all(),$this->user));
        return redirect()->back()->withErrors('Access denied');
    }
    
}
