<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');    // cache for 1 day
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
header("Access-Control-Allow-Headers: X-Requested-With");
date_default_timezone_set('Asia/Jakarta');


require 'vendor/autoload.php';

$mongo  = new \MongoDB\Client($_SERVER['MONGODB_URI']);
$db     = basename($_SERVER['MONGODB_URI']);
$action = $mongo->$db->kiwots;
$ts     = (int) str_replace('.','',microtime(true));

/*
  Insert:
*/
if(isset($_GET['insert'])){

  $data     = (array) json_decode( file_get_contents('php://input') );
  if(is_array($data)){

    $i = 1;
    foreach($data as $t){
      $t->ts = $ts;
      try {
        $is = $action->insertOne((array)$t);
        $es[] = [
          'id'      => $i,
          'status'  => 'success',
          'msg'     => $is->getInsertedId()
        ];
      } catch (Exception $e) {
        $es[] = [
          'id'      => $i,
          'status'  => 'failed',
          'msg'     => 'duplicate'
        ];
      }
    $i++;}

    if(empty($es)){
      $res = [
        'status' => 'failed',
        'msg'   => 'ranodata'
      ];
    }else{
      $res = [
        'count'  => count($es),
        'status' => 'success',
        'data'   => $es
      ];
    }

  }else{
    $res = [
      'status' => 'failed',
      'msg'   => 'must array'
    ];
  }

/*
  Delete
*/
}elseif(isset($_GET['delete'])){

  $data     = json_decode(file_get_contents('php://input'));
  $es       = $action->deleteOne(['_id' => $data->_id]);
  $res = [
    'status' => 'success',
    'data'   => $es->getDeletedCount()
  ];

/*
  Lists
*/
}elseif(isset($_GET['lists'])){

  //filter
  if(isset($_GET['filter'])){
    $fltr = explode(',',$_GET['filter']);
    $ks   = [];
    foreach($fltr as $v){
      $s         = explode(':',$v);
      $ks[$s[0]] = $s[1];
    }
    $filter = $ks;
  }else{
    $filter = [];
  }
  //query
  if(isset($_GET['q'])){
    $que = explode(':',$_GET['q']);
    $filter  = [$que[0]=> ['$regex' => $que[1]]];
  }
  //sort
  $sort         = builder();
  //query mongo
  $kueri    = $action->find(
    $filter,
    $sort
  );
  //manipulasi data
  $dt = [];
  foreach($kueri as $c){
    $dt[] = $c;
  }
  //count result
  $count = $action->count($filter);
  //export
  $res = [
    'count' => $count,
    'data'  => $dt
  ];


/*
  Edit
*/
}elseif(isset($_GET['edit'])){

  $data     = json_decode( file_get_contents('php://input'));
  $uid      = $data->_id;
  unset($data->_id);
  $es       = $action->updateOne(
    ['_id'  => $uid],
    ['$set' => (array) $data]
  );
  $res = [
    'status'    => 'success',
    'match'     => $es->getMatchedCount(),
    'modified'  => $es->getModifiedCount()
  ];

/*
  Else
*/
}else{
  $res = [
    'status' => 'good'
  ];
}

echo json_encode($res);


function builder(){

  if(isset($_GET['limit'])){
    $limit  = $_GET['limit'];
  }else{
    $limit  = 20;
  }
  if(isset($_GET['skip'])){
    $skip  = $_GET['skip'];
  }else{
    $skip  = 0;
  }
  if(isset($_GET['sort'])){
    $st    = explode(':',$_GET['sort']);
    if($st[1]=='asc'){
      $od = 1;
    }else{
      $od = -1;
    }
    $sort  = [$st[0] => $od];
  }else{
    $sort  = ['created' => -1];
  }

  return [
    'sort'  => $sort,
    'limit' => (int) $limit,
    'skip'  => (int) $skip
  ];

}
