<?php

namespace TestModule;
use Zax;

interface ITestComponentFactory {

	/** @return TestControl */
	public function create();

}