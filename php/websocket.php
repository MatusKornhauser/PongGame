<?php
    use Workerman\Worker;
    use Workerman\Lib\Timer;

    require_once __DIR__ . '/../vendor/autoload.php';

    function send_msg_to_client($connection_id, $type, $data = '') {
        global $ws_worker;
        $obj = new stdClass();
		$obj->type = $type;
		$obj->data = $data;
        $connection = $ws_worker->connections[$connection_id];
        $connection->send(json_encode($obj));
    }

    // SSL context.
    $context = [
        'ssl' => [
            'local_cert'  => '/home/xkornhauser/ssl/webte_fei_stuba_sk.pem',
            'local_pk'    => '/home/xkornhauser/ssl/webte.fei.stuba.sk.key',
            'verify_peer' => false,
        ]
    ];
    
    // Create A Worker and Listens 9000 port, use Websocket protocol
    $ws_worker = new Worker("websocket://0.0.0.0:9000", $context);
    
    // Enable SSL. WebSocket+SSL means that Secure WebSocket (wss://). 
    // The similar approaches for Https etc.
    $ws_worker->transport = 'ssl';
 
    // 4 processes
    $ws_worker->count = 1;
    
    // Add a Timer to Every worker process when the worker process start
    $ws_worker->onWorkerStart = function($ws_worker) {
        Timer::add(0.03, function() use($ws_worker) {
            global $game_state, $players, $ball, $starting_player;
            if ($game_state == State::Pending)
                return;

            $game_data = ['players' => [], 'ball' => []];
            foreach ($players as $key => $value) {
                $game_data['players'][$value->playerSide->name] = [
                    'x' => $value->x, 'y' => $value->y, 'width' => $value->width,
                    'height' => $value->height, 'lives' => $value->lives, 'name' => $value->player_name
                ];
            }
            if ($ball) {
                $ball->move();
                $game_data['ball'] = [
                    'x' => $ball->x, 'y' => $ball->y, 'width' => $ball->width,
                    'height' => $ball->height, 'hit_count' => $ball->hit_count
                ];
            }

            if (count($players) == 0) {
                $game_state = State::Pending;
                $starting_player = null;
                foreach($ws_worker->connections as $connection)
                    send_msg_to_client($connection->id, 'game_status', ['state' => $game_state->name, 'grid_size' => GRID]);
                return;
            }
            foreach($ws_worker->connections as $connection)
                send_msg_to_client($connection->id, 'game_update', $game_data);
        });
    };
 
    // Emitted when new connection come
    $ws_worker->onConnect = function($connection) {
        // Emitted when websocket handshake done
        $connection->onWebSocketConnect = function($connection) {
            global $game_state;

            echo "New connection\n";
            send_msg_to_client($connection->id, 'game_status', ['state' => $game_state->name, 'grid_size' => GRID]);
        };
    };
 
    $ws_worker->onMessage = function($connection, $data) {
        $data = json_decode($data, true);
        global $game_state;

        if ($game_state == State::Running) {
            if ($data['type'] == 'paddle_control') {
                global $players;
    
                if (array_key_exists($connection->id, $players))
                    $players[$connection->id]->move($data['direction']);
            }
        }
        else if ($game_state == State::Pending) {
            if ($data['type'] == 'enter_lobby') {
                global $users_in_lobby, $ws_worker;
    
                $users_in_lobby[$connection->id] = $data['name'];
                send_msg_to_client(
                    $connection->id, 'lobby_wait', ['name' => $data['name'], 'connection_count' => count($ws_worker->connections)]
                );
            }
            else if ($data['type'] == 'leave_game') {
                global $users_in_lobby;

                unset($users_in_lobby[$connection->id]);
                send_msg_to_client($connection->id, 'game_status', ['state' => $game_state->name, 'grid_size' => GRID]);
            }
            else if ($data['type'] == 'start_game') {
                global $starting_player;
    
                if ($starting_player == $connection->id)
                    start_game();
            }
        }
        refresh_users();
    };
 
    // Emitted when connection closed
    $ws_worker->onClose = function($connection) {
        global $users_in_lobby, $players;

        echo "Connection closed\n";
        unset($users_in_lobby[$connection->id]);
        unset($players[$connection->id]);

        refresh_users();
    };
    
    // Run worker
    Worker::runAll();
?>
    