<?php
class Movable {
    public $x;
    public $y;
    public $width;
    public $height;

    function normalizeVector($x, $y) {
        $magnitude = sqrt($x * $x + $y * $y);
        if ($magnitude != 0)
            return [$x / $magnitude, $y / $magnitude];
        else
            return [0, 0];
    }

    function rand_f($min, $max) {
        return rand($min * 1000, $max * 1000) / 1000.0;
    }
}

class Paddle extends Movable {
    private const SPEED = 12;
    public $player_name;
    public $lives;
    public $playerSide;

    function __construct($playerSide, $player_name) {
        $this->lives = 3;
        $this->playerSide = $playerSide;
        $this->player_name = $player_name;

        switch ($playerSide) {
            case Side::Left:
                $this->x = GRID * 2;
                $this->y = CANVAS_LENGTH / 2 - PADDLE_LENGTH / 2;
                $this->width = GRID;
                $this->height = PADDLE_LENGTH + 5;
                break;

            case Side::Right:
                $this->x = CANVAS_LENGTH - GRID * 3;
                $this->y = CANVAS_LENGTH / 2 - PADDLE_LENGTH / 2;
                $this->width = GRID;
                $this->height = PADDLE_LENGTH + 5;
                break;

            case Side::Top:
                $this->x = CANVAS_LENGTH / 2 - PADDLE_LENGTH / 2;
                $this->y = GRID * 2;
                $this->width = PADDLE_LENGTH;
                $this->height = GRID + 5;
                break;

            case Side::Bottom:
                $this->x = CANVAS_LENGTH / 2 - PADDLE_LENGTH / 2;
                $this->y = CANVAS_LENGTH - GRID * 3;
                $this->width = PADDLE_LENGTH;
                $this->height = GRID + 5;
                break;

            default:
                error_log('Invalid playerSide number');
                break;
        }
    }

    public function move($direction) {
        switch ($this->playerSide) {
            case Side::Left:
            case Side::Right:
                if ($direction == 'up' && $this->y - self::SPEED > GRID )
                    $this->y -= self::SPEED;
                elseif ($direction == 'down' && $this->y + $this->height + self::SPEED < CANVAS_LENGTH - GRID)
                    $this->y += self::SPEED;
                break;

            case Side::Top:
            case Side::Bottom:
                if ($direction == 'up' && $this->x + $this->width + self::SPEED < CANVAS_LENGTH - GRID )
                    $this->x += self::SPEED;
                elseif ($direction == 'down' && $this->x - self::SPEED > GRID )
                    $this->x -= self::SPEED;
                break;

            default:
                error_log('Invalid playerSide number');
                break;
        }
    }

    public function lose_life() {
        return --$this->lives;
    }
}

class Ball extends Movable {
    private const SPEED = 6;
    private $vx;
    private $vy;
    public $hit_count;

    function __construct() {
        $this->x = CANVAS_LENGTH / 2;
        $this->y = CANVAS_LENGTH / 2;
        $this->width = GRID;
        $this->height = GRID;     
        list($this->vx, $this->vy) = parent::normalizeVector(parent::rand_f(0.5, 1), parent::rand_f(-1, 1));
        $this->hit_count = 0;
    }

    public function move() {
        global $players;
        foreach ($players as $key => $value) { 
            if (is_colliding($this, $value)) {
                $this->hit_count++;
                switch ($value->playerSide) {
                    case Side::Left:
                        $this->vx *= parent::rand_f(-0.5, -1.5);
                        $this->x=$value->x + $value->width;
                        break;

                    case Side::Right:
                        $this->vx *= parent::rand_f(-0.5, -1.5);
                        $this->x=$value->x - $this->width;
                        break;

                    case Side::Top:
                        $this->vy *= parent::rand_f(-0.5, -1.5);
                        $this->y=$value->y + $value->height;
                        break;

                    case Side::Bottom:
                        $this->vy *= parent::rand_f(-0.5, -1.5);
                        $this->y=$value->y - $this->height;
                        break;
                    
                    default:
                        error_log('Invalid playerSide number');
                        break;
                }
            }
        }

        $alive_sides = [];
        foreach ($players as $key => $value) { 
            $alive_sides[] = $value->playerSide;
        }
        if (!in_array(Side::Left, $alive_sides)) {
            if ($this->x < GRID) { // left border
                $this->x = GRID;
                $this->vx *= -1;
                $this->hit_count++;
            }
        }
        if (!in_array(Side::Right, $alive_sides)) {
            if ($this->x + GRID > CANVAS_LENGTH - GRID) { // right border
                $this->x = CANVAS_LENGTH - GRID * 2;
                $this->vx *= -1;
                $this->hit_count++;
            }
        }
        if (!in_array(Side::Top, $alive_sides)) {
            if ($this->y < GRID) { // top border
                $this->y = GRID;
                $this->vy *= -1;
                $this->hit_count++;
            }
        }
        if (!in_array(Side::Bottom, $alive_sides)) {
            if ($this->y + GRID > CANVAS_LENGTH - GRID) { // bottom border
                $this->y = CANVAS_LENGTH - GRID * 2;
                $this->vy *= -1;
                $this->hit_count++;
            }
        }

        if ($this->x < GRID && ($this->y < GRID * 2 || $this->y > CANVAS_LENGTH - GRID * 3)) { // top-left-down or bottom-left-up border
            $this->x = GRID;
            $this->vx *= -1;
            $this->hit_count++;
        }
        else if ($this->x + GRID > CANVAS_LENGTH - GRID && ($this->y < GRID * 2 || $this->y > CANVAS_LENGTH - GRID * 3)) { // top-right-down or bottom-right-up border
            $this->x = CANVAS_LENGTH - GRID * 2;
            $this->vx *= -1;
            $this->hit_count++;
        }
        else if ($this->y < GRID && ($this->x < GRID * 2 || $this->x > CANVAS_LENGTH - GRID * 3)) { // top-left-right or top-right-left border
            $this->y = GRID;
            $this->vy *= -1;
            $this->hit_count++;
        }
        else if ($this->y + GRID > CANVAS_LENGTH - GRID && ($this->x < GRID * 2 || $this->x > CANVAS_LENGTH - GRID * 3)) { // bottom-left-right or bottom-right-left border
            $this->y = CANVAS_LENGTH - GRID * 2;
            $this->vy *= -1;
            $this->hit_count++;
        }

        if ($this->x < 0) {
            self::make_player_lose_life(Side::Left);
            $this->x = CANVAS_LENGTH / 2;
            $this->y = CANVAS_LENGTH / 2;
        }
        else if ($this->x + GRID > CANVAS_LENGTH) {
            self::make_player_lose_life(Side::Right);
            $this->x = CANVAS_LENGTH / 2;
            $this->y = CANVAS_LENGTH / 2;
        }
        else if ($this->y < 0) {
            self::make_player_lose_life(Side::Top);
            $this->x = CANVAS_LENGTH / 2;
            $this->y = CANVAS_LENGTH / 2;
        }
        else if ($this->y - GRID > CANVAS_LENGTH) {
            self::make_player_lose_life(Side::Bottom);
            $this->x = CANVAS_LENGTH / 2;
            $this->y = CANVAS_LENGTH / 2;
        }

        list($this->vx, $this->vy) = parent::normalizeVector($this->vx, $this->vy);
        $this->x += $this->vx * (self::SPEED + ($this->hit_count / 5.0));
        $this->y += $this->vy * (self::SPEED + ($this->hit_count / 5.0)); 
    }

    function make_player_lose_life($playerSide) {
        global $players;

        foreach ($players as $key => $value) {
            if ($value->playerSide == $playerSide) {
                if (!$value->lose_life())
                    unset($players[$key]);
            }
        }
    }
}
?>