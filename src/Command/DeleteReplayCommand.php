<?php declare(strict_types=1);

/*
 * (c) Vladimir "allejo" Jimenez <me@allejo.io>
 *
 * For the full copyright and license information, please view the
 * LICENSE.md file that was distributed with this source code.
 */

namespace App\Command;

use App\Entity\Replay;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class DeleteReplayCommand extends Command
{
    protected static $defaultName = 'app:replay:delete';

    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();

        $this->entityManager = $entityManager;
    }

    protected function configure()
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'Replay ID to delete')
            ->setDescription('Delete a replay file')
            ->setHelp('This commands hard deletes a replay from the database')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $replayRepo = $this->entityManager->getRepository(Replay::class);
        $id = $input->getArgument('id');

        $replay = $replayRepo->find($id);

        if ($replay === null) {
            $output->writeln(sprintf('No replay with an ID of %d was found in the database', $id));

            return;
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Are you sure you want to delete this replay? [y/n] ', false);

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('Exiting...');

            return;
        }

        $this->entityManager->remove($replay);
        $this->entityManager->flush();

        $output->writeln(sprintf('Replay ID %s deleted successfully.', $id));
    }
}
