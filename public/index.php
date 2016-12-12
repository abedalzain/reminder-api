<?php
require '../vendor/autoload.php';
require 'config.php';

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;


$app = new \Slim\App;


$settings = array(
    'driver' => 'mysql',
    'host' => HOST,
    'database' => DBNAME,
    'username' => DBUSER,
    'password' => DBPASS,
    'collation' => 'utf8_general_ci',
    'prefix' => ''
);


$dsn = 'mysql:host='.HOST.';dbname='.DBNAME.';charset=utf8';
$usr = DBUSER;
$pwd = DBPASS;

$pdo = new \Slim\PDO\Database($dsn, $usr, $pwd);

/**
 * Get order by orderid, and return order and user data as json
 *
 * @param int $orderid   The requested order id
 *
 * @return json response
 */
//$app->get('/getnotifications/{userid}', function (Request $request, Response $response) {
////    //get order if from URL request
////    $userid = $request->getAttribute('userid');
////
////    //Get Order model by order id
////    $order = \app\models\Notification::where('user_id', $userid)->get();
////
////    header("Content-Type: application/json");
////
////
////    echo json_encode([$order]);
//
//});


/**
 * Add new reminder to the system
 */
$app->post('/reminder/save', function (Request $request, Response $response) use($pdo) {
    $post = $request->getParsedBody();

    $id = isset($post['id']) ? $post['id'] : 0;
    $content = isset($post['content']) ? $post['content'] : '';
    $userid = isset($post['userid']) ? $post['userid'] : 0;
    $icon = isset($post['icon']) ? $post['icon'] : '';
    $date = isset($post['date']) ? $post['date'] : '';

    $remItem = [];
    $remItem['morning'] = isset($post['morning']) ? $post['morning'] : 0;
    $remItem['afternoon'] = isset($post['afternoon']) ? $post['afternoon'] : 0;
    $remItem['evening'] = isset($post['evening']) ? $post['evening'] : 0;
    $remItem['night'] = isset($post['night']) ? $post['night'] : 0;

    $remItemTime = [];
    $remItemTime['morning'] = isset($post['morningtime']) ? $post['morningtime'] : 0;
    $remItemTime['afternoon'] = isset($post['afternoontime']) ? $post['afternoontime'] : 0;
    $remItemTime['evening'] = isset($post['eveningtime']) ? $post['eveningtime'] : 0;
    $remItemTime['night'] = isset($post['nighttime']) ? $post['nighttime'] : 0;

    $msg = [];

    if ($content == '') {
        $msg[] = 'Please fill the content';
    }
    if ($date == '') {
        $msg[] = 'Please fill the date';
    }

    if (count($msg) > 0) {
        return json_encode($msg);
    }

    if ($id == 0) {
        //Add new record
        $insertStatement = $pdo->insert(array('date', 'create_at', 'title', 'icon'))
            ->into('reminders')
            ->values(array($date, date("Y-m-d H:i:s"), $content, $icon));

        $id = $insertStatement->execute(true);

        foreach ($remItem as $name => $value) {
            if ($value != 0) {
                $insertStatement = $pdo->insert(array('reminder_id', 'item_time', 'title'))
                    ->into('reminder_items')
                    ->values(array($id, $remItemTime[$name], $name));

                $insertStatement->execute(false);
            }
        }

        $msg = ['The record has been saved successfully'];
    } else if ($id > 0) {
        //Edit record
        $updateStatement = $pdo->update(array('date' => $date, 'title' => $content, 'icon' => $icon))
            ->table('reminders')
            ->where('id', '=', $id);

        $affectedRows = $updateStatement->execute();

        $deleteStatement = $pdo->delete()->from('reminder_items')->where('reminder_id', '=', $id);
        $deleteStatement->execute();

        foreach ($remItem as $name => $value) {
            if ($value != 0) {
                $insertStatement = $pdo->insert(array('reminder_id', 'item_time', 'title'))
                    ->into('reminder_items')
                    ->values(array($id, $remItemTime[$name], $name));

                $insertStatement->execute(false);
            }
        }

        $msg = ['The record has been saved successfully'];
    }

    return json_encode($msg);
});

/**
 * Delete reminder from the system
 */
$app->delete('/reminder/{id}', function ($request, $response, $args) use($pdo) {
    $id = intval($args['id']);
    if ($id > 0) {
        $deleteStatement = $pdo->delete()
            ->from('reminders')
            ->where('id', '=', $id);

        $affectedRows = $deleteStatement->execute();

        $deleteStatement = $pdo->delete()
            ->from('reminder_items')
            ->where('reminder_id', '=', $id);

        $deleteStatement->execute();

        if ($affectedRows > 0) {
            return 'The record has been deleted successfully';
        }
    }
});

/**
 * Get one reminder record
 */
$app->get('/reminder/{id}', function ($request, $response, $args) use($pdo) {
    $id = intval($args['id']);

    if ($id > 0) {
        $selectStatement = $pdo->select()
            ->from('reminders')
            ->where('id', '=', $id);

        $stmt = $selectStatement->execute();
        $reminder = $stmt->fetch();

        $selectStatement = $pdo->select()
            ->from('reminder_items')
            ->where('reminder_id', '=', $id);

        $stmt = $selectStatement->execute();
        $reminder_items = $stmt->fetchAll();

        if ($reminder != false) {
            $reminderitems = [];
            foreach ($reminder_items as $row) {
                $reminderitems[] = $row;
            }

            $data = [
                'id' => $reminder['id'],
                'date' => $reminder['date'],
                'title' => $reminder['title'],
                'icon'=> $reminder['icon'],
                'items' => $reminderitems
            ];

            header("Content-Type: application/json");

            return json_encode($data);

        } else {
            return 'No result';
        }
    }
});

/**
 * Get one reminder record
 */
$app->get('/list', function ($request, $response) use($pdo) {
    $selectStatement = $pdo->select()
        ->from('reminders');
        //->where('id', '=', $id);

    $stmt = $selectStatement->execute();
    $result = $stmt->fetchAll();

    $data = [];

    foreach ($result as $row) {
        $selectStatement = $pdo->select()
            ->from('reminder_items')
            ->where('reminder_id', '=', $row['id']);
        $stmt = $selectStatement->execute();
        $itemsresult = $stmt->fetchAll();

        $timing = [];

        foreach ($itemsresult as $item) {
            if ($item['title'] == 'morning') {
                $timing[] = 'morning';
            }
            if ($item['title'] == 'afternoon') {
                $timing[] = 'afternoon';
            }
            if ($item['title'] == 'evening') {
                $timing[] = 'evening';
            }
            if ($item['title'] == 'night') {
                $timing[] = 'night';
            }
        }

        $data[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'icon' => $row['icon'],
            'count' => count($timing),
            'timing' => implode(', ', $timing)
        ];
    }

    header("Content-Type: application/json");

    if ($data != false)
        return json_encode($data);
    else {
        return 'No result';
    }
});


/**
 * Taken or not
 */
$app->get('/taken/{id}/{status}', function ($request, $response, $args) use($pdo) {
    $id = intval($args['id']);
    $status = $args['status'];

    if ($id > 0) {
        $updateStatement = $pdo->update(array('taken' => $status))
            ->table('reminder_items')
            ->where('id', '=', $id);

        $affectedRows = $updateStatement->execute();

        if ($affectedRows > 0) {
            return 'The record has been changed successfully';
        }
    }
});


/**
 * Get one reminder record
 */
$app->get('/getbydate/{date}', function ($request, $response, $args) use($pdo) {
    $date = $args['date'];

    if ($date != '') {
        $selectStatement = $pdo->select()
            ->from('reminders')
            ->where('date', '=', $date);

        $stmt = $selectStatement->execute();
        $reminder = $stmt->fetch();

        $selectStatement = $pdo->select()
            ->from('reminder_items')
            ->where('reminder_id', '=', $reminder['id']);

        $stmt = $selectStatement->execute();
        $reminder_items = $stmt->fetchAll();

        if ($reminder != false) {
            $reminderitems = [];
            foreach ($reminder_items as $row) {
                $reminderitems[] = $row;
            }

            $data = [
                'id' => $reminder['id'],
                'date' => $reminder['date'],
                'title' => $reminder['title'],
                'icon'=> $reminder['icon'],
                'items' => $reminderitems
            ];

            header("Content-Type: application/json");

            return json_encode($data);

        } else {
            return 'No result';
        }
    }
});



$app->run();