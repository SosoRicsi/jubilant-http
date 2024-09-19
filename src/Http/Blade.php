<?php

namespace Jubilant\Http;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\FileViewFinder;
use Illuminate\View\Factory;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;

class Blade
{
	protected $viewFactory;

	/**
	 *| Blade konstruktor, amely inicializálja a Blade motort.
	 *|
	 *| @param array $viewPaths - A nézetek mappáinak tömbje
	 *| @param string $cachePath - A cache mappa útvonala a lefordított sablonokhoz
	 */
	public function __construct(array $viewPaths, string $cachePath)
	{
		// 1. Fájlrendszer inicializálása
		$filesystem = new Filesystem();

		// 2. FileViewFinder inicializálása, ami a nézetfájlokat keresi a megadott helyen
		$fileViewFinder = new FileViewFinder($filesystem, $viewPaths);

		// 3. BladeCompiler létrehozása a fájlrendszer és cache útvonal megadásával
		$bladeCompiler = new BladeCompiler($filesystem, $cachePath);

		// 4. EngineResolver létrehozása a Blade sablon fordításához
		$engineResolver = new EngineResolver();
		$engineResolver->register('blade', function () use ($bladeCompiler) {
			return new CompilerEngine($bladeCompiler);
		});

		// 5. Dispatcher (események) és Service Container (Laravel komponensek kezelésére)
		$dispatcher = new Dispatcher(new Container());

		// 6. ViewFactory létrehozása, amely a nézetek kezeléséért felelős
		$this->viewFactory = new Factory($engineResolver, $fileViewFinder, $dispatcher);
	}

	/**
	 *| Nézet renderelése.
	 *|
	 *| @param string $view - A nézet neve (pl. 'welcome')
	 *| @param array $data - Az átadott adatok a nézethez
	 *| @return string - A renderelt nézet HTML formában
	 */
	public function render(string $view, array $data = []): string
	{
		return $this->viewFactory->make($view, $data)->render();
	}
}
