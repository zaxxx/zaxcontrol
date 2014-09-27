<?php

namespace TestModule;
use Zax,
	Nette;

class TestPresenter extends Nette\Application\UI\Presenter {

	protected $testComponentFactory;

	public function __construct(ITestComponentFactory $testComponentFactory) {
		$this->testComponentFactory = $testComponentFactory;
	}

	public function actionDefault() {

	}

	protected function createComponentTestComponent() {
	    return $this->testComponentFactory->create();
	}

}