<?php

namespace org\Khinenw\xcel\event;

use org\Khinenw\xcel\XcelNgien;
use org\Khinenw\xcel\XcelPlayer;
use pocketmine\event\plugin\PluginEvent;

class PlayerGameChangeEvent extends PluginEvent{
	private $player;
	public static $handlerList;

	public function __construct(XcelPlayer $player){
		parent::__construct(XcelNgien::getInstance());
		$this->player = $player;
	}

	public function getPlayer(){
		return $this->player;
	}
}
