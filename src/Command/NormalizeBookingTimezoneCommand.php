<?php

namespace App\Command;

use App\Entity\Booking;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:normalize-booking-timezone',
    description: 'Shift existing booking dates by a fixed number of hours.'
)]
class NormalizeBookingTimezoneCommand extends Command
{
    public function __construct(
        private BookingRepository $bookingRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'hours',
                null,
                InputOption::VALUE_REQUIRED,
                'Hours to shift bookingDate forward (or negative to shift backward).',
                '8'
            )
            ->addOption(
                'apply',
                null,
                InputOption::VALUE_NONE,
                'Apply updates. Without this flag, command runs in preview mode.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $hours = (int) $input->getOption('hours');
        $apply = (bool) $input->getOption('apply');

        /** @var Booking[] $bookings */
        $bookings = $this->bookingRepository->findAll();
        if (count($bookings) === 0) {
            $io->success('No bookings found.');
            return Command::SUCCESS;
        }

        $io->title('Booking timezone normalization');
        $io->text(sprintf('Total bookings found: %d', count($bookings)));
        $io->text(sprintf('Shift configured: %+d hour(s)', $hours));
        $io->text($apply ? 'Mode: APPLY changes' : 'Mode: PREVIEW only');

        $rows = [];
        $updatedCount = 0;
        foreach ($bookings as $booking) {
            $current = $booking->getBookingDate();
            if (!$current) {
                continue;
            }

            $updated = (clone $current)->modify(sprintf('%+d hours', $hours));
            $rows[] = [
                (string) $booking->getId(),
                $current->format('Y-m-d H:i:s'),
                $updated->format('Y-m-d H:i:s'),
            ];

            if ($apply) {
                $booking->setBookingDate($updated);
                $updatedCount++;
            }
        }

        if (count($rows) > 0) {
            $io->table(['Booking ID', 'Current Date', 'Normalized Date'], array_slice($rows, 0, 20));
            if (count($rows) > 20) {
                $io->note(sprintf('Showing first 20 rows out of %d total.', count($rows)));
            }
        }

        if ($apply) {
            $this->entityManager->flush();
            $io->success(sprintf('Updated %d booking date(s).', $updatedCount));
        } else {
            $io->warning('Preview mode only. Re-run with --apply to save changes.');
        }

        return Command::SUCCESS;
    }
}

