<?php declare(strict_types=1);

namespace Nova\TwoFactor\Command;

use Doctrine\DBAL\Connection;
use Nova\TwoFactor\Service\TwoFactorStore;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Notfall: 2FA eines Admin-Benutzers per Konsole entfernen (Handy und Wiederherstellungscodes verloren,
 * kein anderer Admin vorhanden). Wer SSH-Zugang hat, hat ohnehin vollen Zugriff auf den Shop.
 */
#[AsCommand(name: 'nova-2fa:reset-user', description: 'Zwei-Faktor-Anmeldung eines Admin-Benutzers zurücksetzen')]
class ResetUserCommand extends Command
{
    public function __construct(
        private readonly TwoFactorStore $userStore,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED, 'Benutzername der Administration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = (string) $input->getArgument('username');
        $id = $this->connection->fetchOne('SELECT `id` FROM `user` WHERE `username` = :username', ['username' => $username]);
        if (!\is_string($id)) {
            $output->writeln(\sprintf('<error>Benutzer „%s“ nicht gefunden.</error>', $username));

            return self::FAILURE;
        }

        $this->userStore->disable(Uuid::fromBytesToHex($id));
        $output->writeln(\sprintf('<info>Zwei-Faktor-Anmeldung für „%s“ entfernt. Anmeldung jetzt nur mit Passwort – bitte im Profil neu einrichten.</info>', $username));

        return self::SUCCESS;
    }
}
