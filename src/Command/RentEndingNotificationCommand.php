<?php

namespace App\Command;

use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
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

class RentEndingNotificationCommand extends Command
{
    protected static $defaultName = 'payment:ending:notification';
    private Twig $twig;
    private MailerInterface $mailer;
    private UserRepository $userRepository;
    private TransactionRepository $transactionRepository;

    public function __construct(
        Twig $twig,
        MailerInterface $mailer,
        UserRepository $userRepository,
        TransactionRepository $transactionRepository,
        string $name = null
    ) {
        $this->twig = $twig;
        $this->mailer = $mailer;
        $this->userRepository = $userRepository;
        $this->transactionRepository = $transactionRepository;
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = $this->userRepository->findAll();
        foreach ($users as $user) {
            $expiredTransactions = $this->transactionRepository->findExpiredTransactions($user);
            if (count($expiredTransactions) > 0) {
                $output->writeln('Найдены заканчивающиеся аренды');
                try {
                    $html = $this->twig->render(
                        'email/rentEndNot.html.twig',
                        ['data' => $expiredTransactions]
                    );
                    $email = new Email();
                    $email
                        ->to(new Address($user->getEmail()))
                        ->from(new Address('studyOn@email.com'))
                        ->subject('Окончание срока аренды.')
                        ->html($html);
                    $this->mailer->send($email);
                    $output->writeln('Отправлено письмо');
                } catch (TransportExceptionInterface | LoaderError | RuntimeError | SyntaxError $e) {
                    $output->writeln($e->getMessage());
                    return Command::FAILURE;
                }
            }
        }
        $output->writeln('Уведомления отправлены');
        return Command::SUCCESS;
    }
}
