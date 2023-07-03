<?php 
enum Side : int {
    case Left = 1;
    case Right = 2;
    case Top = 3;
    case Bottom = 4;
} 

enum State {
    case Pending;
    case Running;
}

define('MAX_PLAYER_COUNT', 4);
define('CANVAS_LENGTH', 600);
define('GRID', 20);
define('PADDLE_LENGTH', GRID * 6);

$users_in_lobby = [];
$players = [];
$starting_player = null;
$ball = null;
$game_state = State::Pending;

require_once 'objects.php';
require_once 'websocket.php';

function refresh_users() {
    global $game_state, $users_in_lobby, $starting_player, $ws_worker;

    if ($game_state == State::Running)
        return;

    if (!array_key_exists($starting_player, $users_in_lobby)) {
        $starting_player = array_key_first($users_in_lobby);
        if ($starting_player)
            send_msg_to_client($starting_player, 'can_start_game', '');
    }

    foreach($ws_worker->connections as $connection)
        send_msg_to_client($connection->id, 'connection_count', count($users_in_lobby));
}

function start_game() {
    global $game_state, $players, $users_in_lobby, $ball, $ws_worker;
    $players = [];
    $count = 0;
    foreach ($users_in_lobby as $key => $value) {
        $count++;
        $players[$key] = new Paddle(Side::from($count), $value);
        unset($users_in_lobby[$key]);
        if ($count == 4)
            break;
    }
    $ball = new Ball();

    $game_state = State::Running;
    foreach($ws_worker->connections as $connection)
        send_msg_to_client($connection->id, 'game_status', ['state' => $game_state->name]);
}

function is_colliding($obj1, $obj2) {
    return $obj1->x < $obj2->x + $obj2->width &&
        $obj1->x + $obj1->width > $obj2->x &&
        $obj1->y < $obj2->y + $obj2->height &&
        $obj1->y + $obj1->height > $obj2->y;
}
?>