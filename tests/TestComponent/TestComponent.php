<?php

namespace TestModule;
use Zax;

class TestControl extends Zax\Application\UI\Control {

	/** @persistent */
	public $stringParam = '';

	/** @persistent */
	public $boolParam = FALSE;

	protected $testComponentFactory;

	public function __construct(ITestComponentFactory $testComponentFactory) {
		$this->testComponentFactory = $testComponentFactory;
	}

	public function viewDefault() {

	}

	public function viewFoo() {

	}

	public function beforeRender() {

	}

	public function beforeRenderBar() {

	}

	protected function createComponentTestComponent() {
	    return $this->testComponentFactory->create();
	}

}