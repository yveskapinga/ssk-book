<?php

namespace App\Command;

use App\Ai\GeminiClient;
use App\Book\SearchRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:book:eval-ask-gold', description: 'Offline gold recall check for Ask-the-book hybrid search')]
final class EvalAskGoldCommand extends Command
{
    public function __construct(
        private readonly SearchRepository $search,
        private readonly GeminiClient $gemini,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('fixture', null, InputOption::VALUE_REQUIRED, 'Gold JSON path', dirname(__DIR__, 2).'/tests/fixtures/ask_gold.json')
            ->addOption('slug', null, InputOption::VALUE_REQUIRED, 'Book slug', 'le-prophete-kadima-bakenge')
            ->addOption('fts-only', null, InputOption::VALUE_NONE, 'Skip Gemini query embed (zero vector) — validates FTS/subject recall only');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $goldPath = (string) $input->getOption('fixture');
        $slug = (string) $input->getOption('slug');
        $ftsOnly = (bool) $input->getOption('fts-only');
        $cases = json_decode((string) file_get_contents($goldPath), true, 512, JSON_THROW_ON_ERROR);
        $pass = 0;
        $fail = 0;

        foreach ($cases as $case) {
            if ($ftsOnly) {
                $hits = $this->search->hybridText($slug, (string) $case['question'], 12);
            } else {
                $vector = $this->gemini->embed([(string) $case['question']], 'RETRIEVAL_QUERY')[0];
                $hits = $this->search->hybrid($slug, (string) $case['question'], $vector, 12);
            }
            $pages = [];
            foreach ($hits as $hit) {
                for ($p = (int) $hit['start_page']; $p <= (int) $hit['end_page']; ++$p) {
                    $pages[$p] = true;
                }
            }
            $expected = array_map('intval', $case['expected_pages']);
            $pageHit = count(array_intersect($expected, array_keys($pages))) > 0;
            $needles = $case['must_include_any'] ?? [];
            $text = implode("\n", array_column($hits, 'content'));
            $needleHit = $needles === [];
            foreach ($needles as $needle) {
                if (mb_stripos($text, (string) $needle) !== false) {
                    $needleHit = true;
                    break;
                }
            }
            $ok = $pageHit && $needleHit;
            $ok ? ++$pass : ++$fail;
            $topPages = array_slice(array_keys($pages), 0, 8);
            $io->writeln(sprintf(
                '%s %s | pages=%s | pageHit=%s needleHit=%s',
                $ok ? 'PASS' : 'FAIL',
                $case['id'],
                implode(',', $topPages),
                $pageHit ? 'y' : 'n',
                $needleHit ? 'y' : 'n',
            ));
        }

        $io->writeln(sprintf('%d/%d passed', $pass, $pass + $fail));

        return $fail === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
