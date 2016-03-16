<?php namespace App\Http\Services;

use App\Models\Document;
use App\Models\DocumentHistory;
use App\Models\DocumentSharedWith;
use App\Models\DocumentVersion;
use App\Models\DocumentType;
use DB,Validator,File;

class DocumentService
{
    protected
            $document,
            $documentHistory,
            $documentSharedWith,
            $documentVersion,
            $documentType,
            $documentPath;

    /**
     * Instantiate a new User instance.
     */
    public function __construct( 
            Document $document,
            DocumentHistory $documentHistory,
            DocumentSharedWith $documentSharedWith,
            DocumentType $documentType,
            DocumentVersion $documentVersion
        )
    {
        $this->documentPath        = public_path().'/uploads/users/documents/';
        $this->document            = $document;
        $this->documentType        = $documentType;
        $this->documentVersion     = $documentVersion;
        $this->documentHistory     = $documentHistory;
        $this->documentSharedWith  = $documentSharedWith;
    }

    /*
     * Get user documnt groups and types
     * 
     * @param Int $user_id
     * @return Array
     */
    public function getUserDocumentGroupedTypes( $user_id )
    {
        $sql = "
            SELECT 
              `dg`.`title` AS `group_title`,
              `dt`.`id` AS `type_id`,
              `dt`.`group_id` AS `group_id`,
              `dt`.`title` AS `type_title`,
              `dv`.`version_id` AS `document_version_id`,
              `d`.`id` AS `document_id`,
              `d`.`name` AS `document_name`,
              `d`.`title` AS `document_title` 
            FROM
              `document_groups` `dg` 
              INNER JOIN `document_types` `dt` 
                ON `dg`.`id` = `dt`.`group_id`
              LEFT OUTER JOIN `documents` `d` 
                ON (`d`.`document_type_id` = `dt`.`id` AND (`d`.`user_id` = {$user_id} OR `d`.`id` = (SELECT document_id FROM `document_shared_with` WHERE document_id = 1)))
              LEFT OUTER JOIN `document_versions` `dv`
              ON `dv`.`document_id` = `d`.`id`
              WHERE `dt`.`user_id` = 0 OR `dt`.`user_id` = {$user_id}
              AND `dt`.`verified` = 1"
              ;
        $groupAndTypes = DB::select(DB::raw($sql));
        $groupedTypes = [];
        foreach ( $groupAndTypes as $groupAndType )
        {
            $groupedTypes[$groupAndType->group_title][$groupAndType->type_id]['type_title'] = $groupAndType->type_title;
            $groupedTypes[$groupAndType->group_title][$groupAndType->type_id]['document_id'] = $groupAndType->document_id;
            $groupedTypes[$groupAndType->group_title][$groupAndType->type_id]['document_version_id'] = $groupAndType->document_version_id;
            $groupedTypes[$groupAndType->group_title][$groupAndType->type_id]['document_title'] = $groupAndType->document_title;
            $groupedTypes[$groupAndType->group_title][$groupAndType->type_id]['document_name'] = $groupAndType->document_name;
        }
        //dd($groupedTypes);
        return $groupedTypes;
    }

    /*
     * Uploads documents when user on step3
     * 
     * @param $documents Array
     * @return Boolean
    **/
    public function uploadDocuments($request,$user)
    {
        $validator = Validator::make($request,
                [
                    'file'=>'required',
                    'type_id'=>'required'
                ]);
        if($validator->fails())
        {
            return ['success'=>false,'message'=>$validator->messages()];
        }
        $file = $request['file'];
        $documentNewName = uniqid().'.'.$file->getClientOriginalExtension();
        //create document
        $documentData['user_id']          = $user->id;
        $documentData['name']             = $documentNewName;
        $documentData['title']            = $request['type_title'];
        $documentData['document_type_id'] = $request['type_id'];
        $document = $this->document->create($documentData);
        $file->move($this->documentPath, $documentNewName);
        if($document)
        {
            //Create version
            $versionData['document_id'] = $document->id;
            $versionData['version_id']  = $document->id;
            $this->documentVersion->create($versionData);
            //Create history
            $historyData['document_id'] = $document->id;
            $historyData['type'] = 'created';
            $this->documentHistory->create($historyData);
            return [
                'success'  => true,
                'message'  => ['Document successfuly uploaded'],
                'document' => $document
            ];
        }
        return [
                'success'=>false,
                'message'=>['Something wet wrong.Cant upload document']
            ];
    }

    public function getLimitedDocumentsByUserId( $user_id )
    {
        return $this->document
                ->where('user_id',$user_id)
                ->orderBy('id','DESC')
                ->limit(10)
                ->get();
    }

    /*
     * Delete user all documents
     * 
     * @param Int $user_id
     * @param Array $request
     * @return Boolean
     */
    public function shareWith( $request, $shared_user )
    {
        $validator = Validator::make(
                        ['shared_users'=>$request['shared_users']],
                        ['shared_users'=>'required'],
                        ['required'=>'Select users field cannot be blank']);
        if( $validator->fails() )
            return ['success'=>false,'message'=>$validator->messages()];
        $shared_document = $this->document->find($request['document_id']);
        if(File::exists($this->documentPath.$shared_document->name))
        {
            //Copying document for sahred users
            foreach( explode(',', $request['shared_users']) as $user_id )
            {
                $sharedWithData['user_id']     = $user_id;
                $sharedWithData['document_id'] = $shared_document->id;
                $this->documentSharedWith->create($sharedWithData);
            }
            return [ 'success'=>true, 'message'=>['Successfuly shared'] ];
        }
        return [ 'success'=>false, 'message'=>['This file does not exist'] ];
    }

    /*
     * Get uploaded documents history
     * 
     * @param Array $request
     * @param Object $user
     * @return Array
     */
    public function getHistory( $request, $user )
    {
        $version      = $this->getVersions($request,$user->id);
        $shared_with  = $this->getSharedWith($request);
        $history      = $this->getHitory($request);
        return [
                    'versions'   => $version,
                    'shared_with'=> $shared_with,
                    'histories'=> $history
                ];
    }

    /*
     * Get uploaded documents Versions
     * 
     * @param Int $document_id
     * @return Array
     */
    public function getVersions( $request )
    {
        $sql = "
            SELECT 
              CONCAT(
                `u`.`first_name`,
                ' ',
                `u`.`last_name`
              ) AS `user_name`,
              `u`.`id` AS `user_id`,
              `u`.`file` AS `user_file`,
              `d`.`id` AS `document_id`,
              `d`.`title` AS `document_title`,
              `d`.`name` AS `document_name`,
              DATE_FORMAT(`d`.`updated_at`, '%d %M %Y') AS `document_updated_at`
              FROM `users` `u`
              INNER JOIN `documents` `d`
              ON `u`.`id` = `d`.`user_id`
              INNER JOIN `document_versions` `dv`
              ON `d`.`id` = `dv`.`document_id` 
              LEFT OUTER JOIN `document_shared_with` `dsw`
              ON `u`.`id` = `dsw`.`user_id`
              WHERE `dv`.`version_id` = {$request['document_version_id']}
            ";
        return DB::select(DB::raw($sql));
    }

    /*
     * Get uploaded documents Sahred with users
     * 
     * @param Int $document_id
     * @param Int $user_id
     * @return Array
     */
    public function getSharedWith( $request )
    {
        $sql = "
            SELECT 
              CONCAT(
                `u`.`first_name`,
                ' ',
                `u`.`last_name`
              ) AS `user_name`,
              `u`.`id` AS `user_id` 
             FROM `users` `u`
              INNER JOIN `documents` `d`
              ON `u`.`id` = `d`.`user_id`
              INNER JOIN `document_versions` `dv`
              ON `d`.`id` = `dv`.`document_id` 
              LEFT OUTER JOIN `document_shared_with` `dsw`
              ON `u`.`id` = `dsw`.`user_id`
              WHERE `dv`.`version_id` = {$request['document_version_id']}
            ";
        return DB::select(DB::raw($sql));
    }

    /*
     * Get uploaded documents Sahred with users
     * 
     * @param Int $document_id
     * @param Int $user_id
     * @return Array
     */
    public function getHitory( $request )
    {
        $sql = "
            SELECT 
              CONCAT(
                `u`.`first_name`,
                ' ',
                `u`.`last_name`
              ) AS `user_name`,
              `u`.`id` AS `user_id`,
              `u`.`file` AS `user_file`,
              `d`.`id` AS `document_id`,
              `d`.`title` AS `document_title`,
              `d`.`name` AS `document_name`,
              `dh`.`type` AS `history_type`,
              DATE_FORMAT(`dh`.`created_at`, '%d %M %Y') AS `history_created_at` 
            FROM
              `users` `u` 
              INNER JOIN `documents` `d` 
                ON `u`.`id` = `d`.`user_id` 
              INNER JOIN `document_versions` `dv` 
                ON `d`.`id` = `dv`.`document_id` 
              INNER JOIN `document_histories` `dh` 
                ON `d`.`id` = `dh`.`document_id` 
              LEFT OUTER JOIN `document_shared_with` `dsw` 
                ON `u`.`id` = `dsw`.`user_id` 
            WHERE `dv`.`version_id` = {$request['document_version_id']}";
        return DB::select(DB::raw($sql));
    }

    /*
     * Get uploaded documents Sahred with users
     * 
     * @param Int $document_id
     * @param Int $user_id
     * @return Array
     */
    public function getSharedUsers( $request ,$user_id)
    {
        $sql = "
            SELECT 
              CONCAT(
                `u`.`first_name`,
                ' ',
                `u`.`last_name`
              ) AS `user_name`,
              `u`.`id` AS `user_id` 
              FROM `users` `u`
              INNER JOIN `document_shared_with` `dsw`
              ON `dsw`.`user_id` = `u`.`id`
              INNER JOIN `document_versions` `dv`
              ON `dv`.`version_id` = `dsw`.`document_id`
              WHERE `dv`.`version_id` = {$request['document_verion_id']}
        ";
        $team_users = \App::make('App\Http\Services\UserService')->getUserTeam($user_id);
        $shared_users = DB::select(DB::raw($sql));
        $arr = [];
        $not_shared_users = [];
        $shared_users_ids = [];
        foreach($shared_users as $shared_user)
		{
            $shared_users_ids [] = $shared_user->user_id;
        }
        foreach ($team_users as $team_user)
        {
            if(!in_array($team_user->id,$shared_users_ids) and $team_user->id != $user_id){
                $arr['id']   = $team_user->id;
                $arr['text'] = $team_user->first_name.' '.$team_user->last_name;
                $not_shared_users['items'][] = $arr;
            }
        }
        //dd($not_shared_users);
        return $not_shared_users;
    }
    
    /*
     * Delete shared whith user into history
     * 
     * @param Int $user_id
     * @return Boolean
     */
    public function deleteSharedWhithUserHistory( $user_id )
    {
        $delete = $this->documentHistory
                    ->where('user_id',$user_id)
                    ->orWhere('shared_user_id',$user_id)
                    ->delete();
        if($delete)
            return ['success'=>true,'message'=>'Successfuly deleted'];
        return ['success'=>false,'message'=>'Something wet wrong.Cant delete'];
    }

    /*
     * Update document
     * 
     * $param Object $file
     * @return Object
     */
    public function updateDocumentById1($file,$id,$user_id)
    {
        $validator = Validator::make(
                        ['file'=>$file],
                        ['file'=>'required']
                     );
        if($validator->fails())
            return ['success'=>false,'message'=>$validator->messages()];
        $document = $this->document
                    ->where('id',$id)
                    ->where('user_id',$user_id)->first();
        if($document)
        {
            File::delete($this->documentPath.$document->name);
            $updatedName = uniqid().'.'.$file->getClientOriginalExtension();
            $file->move($this->documentPath,$updatedName);
            $document->update(['name'=>$updatedName,'updated_at'=>date('Y-m-d H:i:s')]);
            return ['success'=>true,'document_name'=>$updatedName,'document_title'=>$document->title];
        }
        return ['success'=>false,'message'=>'Cannot get document'];
    }
    /*
     * Update document
     * 
     * $param Object $file
     * @return Object
     */
    public function updateDocumentById($file,$id,$user_id)
    {
        $validator = Validator::make(
                        ['file'=>$file],
                        ['file'=>'required']
                     );
        if($validator->fails())
            return ['success'=>false,'message'=>$validator->messages()];
        $document = $this->document
                    ->where('id',$id)
                    ->orWhere('version_id',$id)
                    ->where('user_id',$user_id)->first();
        if($document)
        {
            $updatedName = uniqid().'.'.$file->getClientOriginalExtension();
            $data['user_id'] = $user_id;
            $data['document_type_id'] = $document->document_type_id;
            $data['version_id'] = $document->id;
            $data['name'] = $updatedName;
            $data['title'] = $document->title;
            $data['viewed_at'] = date('Y-m-d H:i:s');
            $new_document = $document->create($data);
            $this->documentVersion->create([ 'document_id'=>$new_document->id, 'version_id'=>$id ]);
            $file->move($this->documentPath,$updatedName);
            return ['success'=>true,'document_name' => $updatedName,'document_title'=>$document->title];
        }
        return ['success'=>false,'message'=>'Cannot get document'];
    }

    /*
     * Create new document type
     */
    public function createNewDocumentType( $request, $user )
    {
        if(empty($request['group_id']))
            $request['group_id'] = 1;
        $validator = Validator::make(
                    [
                        'verified_by' => $request['users'],
                        'document_name' => $request['title'],
                        'group' => $request['group_id']
                    ],
                    [
                        'verified_by' => '',
                        'document_name' => 'required',
                        'group' => 'required'
                    ]
                );
        if($validator->fails())
        {
            return ['success'=>false,'message'=>$validator->messages()];
        }
        $type = $this->documentType->create([
                    'group_id' => $request['group_id'],
                    'user_id'  => $user->id,
                    'title'  => $request['title']
                ]);
        if($type){
            $request['type_id'] = $type->id;
            return $this->uploadDocuments($request,$user);
        }
        return ['success'=>false,'message'=>'Something wet wrong.Cant create document type'];
    }
}