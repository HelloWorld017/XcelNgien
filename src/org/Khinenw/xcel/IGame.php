<?php

namespace org\Khinenw\xcel;

interface IGame{
	public static function getGameName();

	//will be used in gamecore framework and json files
	public static function getUniqueGameName();
}
