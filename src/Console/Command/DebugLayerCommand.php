<?php

declare(strict_types=1);

namespace Qossmic\Deptrac\Console\Command;

use Qossmic\Deptrac\Configuration\ConfigurationLayer;
use Qossmic\Deptrac\Configuration\Loader;
use Qossmic\Deptrac\Console\Command\Exception\SingleDepfileIsRequiredException;
use Qossmic\Deptrac\Console\Symfony\Style;
use Qossmic\Deptrac\Console\Symfony\SymfonyOutput;
use Qossmic\Deptrac\LayerAnalyser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DebugLayerCommand extends Command
{
    use DefaultDepFileTrait;

    protected static $defaultName = 'debug:layer';

    private LayerAnalyser $analyser;
    private Loader $loader;

    public function __construct(
        LayerAnalyser $analyser,
        Loader $loader
    ) {
        parent::__construct();

        $this->analyser = $analyser;
        $this->loader = $loader;
    }

    protected function configure(): void
    {
        $this->addArgument('layer', InputArgument::OPTIONAL, 'Layer to debug');
        $this->addOption('depfile', null, InputOption::VALUE_OPTIONAL, 'Path to the depfile');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputStyle = new Style(new SymfonyStyle($input, $output));
        $depfile = $input->getOption('depfile') ?? $this->getDefaultFile(new SymfonyOutput($output, $outputStyle));

        if (!is_string($depfile)) {
            throw SingleDepfileIsRequiredException::fromArgument($depfile);
        }

        /** @var ?string $layer */
        $layer = $input->getArgument('layer');

        $configuration = $this->loader->load($depfile);

        $configurationLayers = array_map(
            static fn (ConfigurationLayer $configurationLayer) => $configurationLayer->getName(),
            $configuration->getLayers()
        );

        if (null !== $layer && !in_array($layer, $configurationLayers, true)) {
            $outputStyle->error('Layer not found.');

            return 1;
        }

        $layers = null === $layer ? $configurationLayers : (array) $layer;

        foreach ($layers as $layer) {
            $matchedLayers = array_map(
                static fn (string $token) => (array) $token,
                $this->analyser->analyse($configuration, $layer)
            );

            $table = new Table($output);
            $table->setHeaders([$layer]);
            $table->setRows($matchedLayers);
            $table->render();
        }

        return 0;
    }
}
