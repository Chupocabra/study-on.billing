<?php

namespace App\Command;

use App\Repository\TransactionRepository;
use App\Service\Twig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class PaymentReportCommand extends Command
{
    protected static $defaultName = 'payment:report';

    private Twig $twig;
    private MailerInterface $mailer;
    private TransactionRepository $transactionRepository;

    public function __construct(
        Twig $twig,
        MailerInterface $mailer,
        TransactionRepository $transactionRepository,
        string $name = null
    ) {
        $this->twig = $twig;
        $this->mailer = $mailer;
        $this->transactionRepository = $transactionRepository;
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $start = new \DateTimeImmutable("first day of this month");
        $end = $start->add(new \DateInterval('P1M'));
        $transactions = $this->transactionRepository->findTransactionsForReport($start, $end);
        if (count($transactions) > 0) {
            $output->writeln('Найдены транзакции в этом месяце');
            $total = 0;
            foreach ($transactions as $t) {
                $total += $t['total'];
            }
            try {
                $html = $this->twig->render(
                    'email/paymentReport.html.twig',
                    [
                        'transactions' => $transactions,
                        'date' => ['start' => $start, 'end' => $end],
                        'total' => $total
                    ]
                );
                $email = new Email();
                $email
                    ->to(new Address($_ENV['REPORT_MAIL']))
                    ->from(new Address('studyOn@email.com'))
                    ->subject('Отчет по оплатам за месяц')
                    ->html($html);
                $this->mailer->send($email);
                $output->writeln('Отправлено письмо');
            } catch (TransportExceptionInterface | LoaderError | RuntimeError | SyntaxError $e) {
                $output->writeln($e->getMessage());
                return Command::FAILURE;
            }
        }
        $output->writeln('Команда закончила выполнение');
        return Command::SUCCESS;
    }
}
