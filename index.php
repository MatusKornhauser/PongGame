<?php 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pong</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous">
    <link rel="stylesheet" href="./css/style.css">
</head>

<body>
    <div class="container">
        <div class="align-items-center justify-content-center">
            <canvas width="600" height="600" id="game" class="d-none"></canvas>

            <div id="lobby" class="justify-content-center align-items-center">
                <div id="lobby-name-screen" class="row w-100">
                    <div class="col-8 mb-3 mx-auto">
                        <p class="fs-2 text-black">Game Pong</p>
                        <label for="input-player-name" class="form-label fs-2 text-black">Enter name</label>
                        <input type="text" id="input-player-name" class="form-control" required>
                    </div>
                    <div class="d-grid col-6 mx-auto mt-3">
                        <button type="button" id="btn-enter-lobby" class="btn btn-dark">Enter lobby</button>
                    </div>
                </div>
                <div id="lobby-wait-screen" class="row w-100 d-none">
                    <div class="col-8 mb-3 mx-auto text-center">
                        <p class="fs-2 text-black">Player name: <span id="lobby-player-name"></span></p>
                        <p class="fs-3 text-black">Player Count: <span id="lobby-connection-count">1</span></p>
                    </div>
                    <div class="d-grid gap-2 col-6 mx-auto mt-3">
                        <button type="button" id="btn-start-game" class="btn btn-dark d-none">Start Game</button>
                        <button type="button" id="btn-leave-game" class="btn btn-dark">Leave</button>
                    </div>
                </div>
            </div>
        </div> 
    </div>
    <script src="https://code.jquery.com/jquery-3.6.3.min.js" integrity="sha256-pvPw+upLPUjgMXY0G+8O0xUf+/Im1MZjXxxgOcBQBXU=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js" integrity="sha384-w76AqPfDkMBDXo30jS1Sgez6pr3x5MlQ1ZAGC+nuZB+EYdgRZgiwxhTBTkF7CXvN" crossorigin="anonymous"></script>
    <script>
            const canvas = document.getElementById('game');
            const context = canvas.getContext('2d');
            const ws = new WebSocket("wss://site130.webte.fei.stuba.sk:9000/pong/php/game.php");
                
                // when the connection is established
                ws.onopen = function(event) {
                    console.log("Connected to WebSocket server.");
                };
                
                // when the server sends a message
                ws.onmessage = function(event) {
                    const msg = JSON.parse(event.data);
                    if (msg.type == 'game_status') {
                        if (msg.data.state == 'Pending') {
                            $('#game').addClass('d-none');
                            $('#lobby').removeClass('d-none');
                            $('#lobby-name-screen').removeClass('d-none');
                            $('#lobby-wait-screen').addClass('d-none');
                            $('#btn-start-game').addClass('d-none');
                            grid = msg.data.grid_size;
                            isRunning = false;
                        }
                        else if (msg.data.state == 'Running') {
                            $('#game').removeClass('d-none');
                            $('#lobby').addClass('d-none');
                            isRunning = true;
                            requestAnimationFrame(loop);
                        }
                    }
                    else if (msg.type == 'lobby_wait') {
                        $('#lobby-name-screen').addClass('d-none');
                        $('#lobby-wait-screen').removeClass('d-none');
                        $('#lobby-player-name').text(msg.data.name);
                        $('#lobby-connection-count').text(msg.data.connection_count);
                    }
                    else if (msg.type == 'connection_count') {
                        $('#lobby-connection-count').text(msg.data);
                    }
                    else if (msg.type == 'can_start_game') {
                        $('#btn-start-game').removeClass('d-none');
                
                    }
                    else if (msg.type == 'game_update') {
                        gameData = msg.data;
                    }
                    // console.log("Received message:", event.data);
                };
                
                // when the connection is closed
                ws.onclose = function(event) {
                    console.log("Disconnected from WebSocket server.");
                };
                
                // when there is an error with the connection
                ws.onerror = function(event) {
                    console.error("WebSocket error:", event);
                };
                
                function sendMessageToWS(obj) {
                    ws.send(JSON.stringify(obj));
                }
                
                $('#btn-enter-lobby').on('click', () => {
                    const playerName = $('#input-player-name').val().trim();
                    if (playerName == '') {
                        alert('Name is required');
                        return;
                    }
                    sendMessageToWS({type: 'enter_lobby', name: playerName});
                });
                
                $('#btn-start-game').on('click', () => {
                    sendMessageToWS({type: 'start_game'});
                })
                
                $('#btn-leave-game').on('click', () => {
                    sendMessageToWS({type: 'leave_game'});
                })
                
                $(document).on('keydown', (function(e) {
                    if (e.which == 38 || e.which == 39) // up or right arrow key pressed
                        sendMessageToWS({type: 'paddle_control', direction: 'up'});  
                    else if (e.which == 37 || e.which == 40) // down or left arrow key pressed
                        sendMessageToWS({type: 'paddle_control', direction: 'down'});
                }));
                    let isRunning = false;
                    let gameData = null;
                    let grid = 15;

            function loop() {
                var my_gradient=context.createLinearGradient(0, 0, 170, 0);
                my_gradient.addColorStop(0, "#a8d3c9");
                my_gradient.addColorStop(1, "#aab6ee");
                if (gameData != null) {
                    context.fillStyle = my_gradient;
                    context.fillRect(0, 0, canvas.width, canvas.height);

                    // draw paddles
                    let aliveSides = [];
                    context.fillStyle = 'black';
                    context.font = "30px Arial";
                    $.each(gameData.players, (playerSide, playerData) => {
                        aliveSides.push(playerSide);
                        context.fillRect(playerData.x, playerData.y, playerData.width, playerData.height);
                        if (playerSide == 'Left') {
                            context.fillText(playerData.name, grid * 7, canvas.height / 2 - 15);
                            context.fillText(playerData.lives + 'lives', grid * 6, canvas.height / 2 + 15);
                        }
                        else if (playerSide == 'Right') {
                            context.fillText(playerData.name, canvas.width - grid * 7, canvas.height / 2 - 15);
                            context.fillText(playerData.lives + ' lives', canvas.width - grid * 7, canvas.height / 2 + 15);
                        }
                        else if (playerSide == 'Top') {
                            context.fillText(playerData.name, canvas.width / 2, grid * 6);
                            context.fillText(playerData.lives + ' lives', canvas.width / 2, grid * 6 + 30);
                        }
                        else if (playerSide == 'Bottom') {
                            context.fillText(playerData.name, canvas.width / 2, canvas.height - grid * 6 - 30);
                            context.fillText(playerData.lives + ' lives', canvas.width / 2, canvas.height - grid * 6);
                        }
                    });

                    context.fillRect(gameData.ball.x, gameData.ball.y, gameData.ball.width, gameData.ball.height);
                    context.textAlign = "center";
                    context.fillText(' Count: '+ gameData.ball.hit_count, canvas.width / 2 + 195, canvas.height / 2 + 200);

                    context.fillStyle = 'black';
                    if (!aliveSides.includes('Left')) {
                        context.fillRect(0, 0, grid, canvas.height); 
                    }
                    if (!aliveSides.includes('Right')) {
                        context.fillRect(canvas.width - grid, 0, grid, canvas.height);
                    }
                    if (!aliveSides.includes('Top')) {
                        context.fillRect(0, 0, canvas.width, grid); 
                    }
                    if (!aliveSides.includes('Bottom')) {
                        context.fillRect(0, canvas.height - grid, canvas.width, canvas.height); 
                    }

                    context.fillRect(0, 0, grid, grid * 2); // top-left-down
                    context.fillRect(0, canvas.height - grid * 2, grid, grid * 2); // bottom-left-up
                    context.fillRect(canvas.width - grid, 0, grid, grid * 2); // top-right-down
                    context.fillRect(canvas.width - grid, canvas.height - grid * 2, grid, grid * 2) // bottom-right-up
                    context.fillRect(0, 0, grid * 2, grid); // top-left-right
                    context.fillRect(canvas.width - grid * 2, 0, grid * 2, grid); // top-right-left
                    context.fillRect(0, canvas.height - grid, grid * 2, grid); // bottom-left-right
                    context.fillRect(canvas.width - grid * 2, canvas.height - grid, grid * 2, grid) // bottom-right-left
                }
                if (isRunning)
                    requestAnimationFrame(loop);
            }
    </script>
</body>
</html>