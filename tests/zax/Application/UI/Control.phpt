<?php

namespace Zax\Tests\Application\UI;
use Tester,
	Tester\Assert,
	Nette,
	Zax,
	ZaxCMS;

$container = require __DIR__ . '/../../../bootstrap.php';

class ControlTest extends Tester\TestCase {

	/** @var Nette\DI\Container */
	private $container;

	/** @var Zax\Application\UI\Control */
	private $control;

	/** @var Nette\Application\UI\Presenter */
	private $presenter;

	public function __construct(Nette\DI\Container $container) {
		$this->container = $container;
		$pFactory = $container->getByType('Nette\Application\IPresenterFactory');
		$this->presenter = $pFactory->createPresenter('Test:Test');
	}

	public function setUp() {
		if($this->control !== NULL ){
			$this->presenter->removeComponent($this->control);
		}
		$this->control = $this->presenter->getComponent('testComponent');
	}

	public function testViewNotExist() {
		$this->control->setView('abc');
		Assert::exception(function() {$this->control->run();}, 'Nette\Application\UI\BadSignalException');
	}

	public function testBeforeRenderNotExist() {
		$this->control->setView('foo');
		Assert::exception(function() {$this->control->run('bad');}, 'Nette\Application\UI\BadSignalException');
	}

	public function testTemplatePath() {
		$path1 = $this->control->getTemplatePath('Foo');
		Assert::same($this->tp('Foo'), $path1);

		$path2 = $this->control->getTemplatePath('Foo', 'Bar');
		Assert::same($this->tp('Foo.Bar'), $path2);
	}

	public function testView() {
		$this->control->setView('foo');
		ob_start();
		$this->control->run();
		$output = ob_get_clean();
		Assert::same('View: Foo', $output);
	}

	public function testRenderHack() {
		$this->control->setView('foo');
		ob_start();
		$this->control->render();
		$output = ob_get_clean();
		Assert::same('View: Foo', $output);

		ob_start();
		$this->control->renderBar();
		$output = ob_get_clean();
		Assert::same('View: Foo, Render: Bar', $output);
	}

	public function testAjaxRecursive() {
		$this->control->enableAjax();
		$control = $this->control;
		for($i=0;$i<100;$i++) {
			Assert::true($control->isAjaxEnabled());
			$control = $control->getComponent('testComponent');
		}
	}

	public function testDisableAjaxFor() {
		$this->control->enableAjax();

		$this->control[str_repeat('testComponent-', 49) . 'testComponent']
			->disableAjaxFor(['testComponent']);

		$control = $this->control;
		for($i=0;$i<100;$i++) {
			if($i>50) {
				Assert::false($control->isAjaxEnabled());
			} else {
				Assert::true($control->isAjaxEnabled());
			}
			$control = $control->getComponent('testComponent');
		}
	}

	public function testRedrawNothing() {
		$this->control->enableAjax();

		$deepest = $this->control[str_repeat('testComponent-', 50) . 'testComponent'];
		Assert::true($deepest->isAjaxEnabled());

		$randomDeep = $this->control[str_repeat('testComponent-', rand(1,20)) . 'testComponent'];
		$randomDeep->redrawNothing();

		Assert::true($randomDeep->isAjaxEnabled());
		Assert::true($deepest->isAjaxEnabled());
		Assert::true($this->control->isAjaxEnabled());

		Assert::false($randomDeep->isControlInvalid());
		Assert::false($deepest->isControlInvalid());
		Assert::false($this->control->isControlInvalid());
	}

	public function testForward() {
		$this->control->redrawControl();

		$this->control->forward('this', ['stringParam' => 'Foo']);
		Assert::same('Foo', $this->control->stringParam);

		$this->control->forward('this', ['boolParam' => TRUE]);
		Assert::true($this->control->boolParam);

		$this->control->forward('this', ['view' => 'Foo']);
		ob_start();
		$this->control->run();
		$output = ob_get_clean();
		Assert::same('View: Foo', $output);

		Assert::true($this->control->isControlInvalid());

		Assert::exception(function() {$this->control->forward('badsignal!');}, 'Nette\Application\UI\BadSignalException');
	}

	private function tp($name) {
		return dirname($this->control->reflection->fileName) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $name . '.latte';
	}

}

$test = new ControlTest($container);
$test->run();
