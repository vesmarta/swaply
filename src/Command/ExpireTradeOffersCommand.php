<?php

namespace App\Command;

use App\Repository\TradeOfferRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:offers:expire',
    description: 'Mark pending trade offers past their expiry date as expired',
)]
class ExpireTradeOffersCommand extends Command
{
    public function __construct(private readonly TradeOfferRepository $offers)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = $this->offers->expirePending();
        $io->success(sprintf('%d pending offer(s) marked as expired.', $count));

        return Command::SUCCESS;
    }
}
