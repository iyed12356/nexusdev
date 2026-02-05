<?php

namespace App\Tests\Controller;

use App\Entity\Player;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PlayerControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $playerRepository;
    private string $path = '/player/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->playerRepository = $this->manager->getRepository(Player::class);

        foreach ($this->playerRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Player index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first()->text());
    }

    public function testNew(): void
    {
        $this->markTestIncomplete();
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'player[gamerTag]' => 'Testing',
            'player[mainGame]' => 'Testing',
            'player[stats]' => 'Testing',
            'player[bio]' => 'Testing',
            'player[deletedAt]' => 'Testing',
            'player[user]' => 'Testing',
            'player[teams]' => 'Testing',
        ]);

        self::assertResponseRedirects($this->path);

        self::assertSame(1, $this->playerRepository->count([]));
    }

    public function testShow(): void
    {
        $this->markTestIncomplete();
        $fixture = new Player();
        $fixture->setGamerTag('My Title');
        $fixture->setMainGame('My Title');
        $fixture->setStats('My Title');
        $fixture->setBio('My Title');
        $fixture->setDeletedAt('My Title');
        $fixture->setUser('My Title');
        $fixture->setTeams('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Player');

        // Use assertions to check that the properties are properly displayed.
    }

    public function testEdit(): void
    {
        $this->markTestIncomplete();
        $fixture = new Player();
        $fixture->setGamerTag('Value');
        $fixture->setMainGame('Value');
        $fixture->setStats('Value');
        $fixture->setBio('Value');
        $fixture->setDeletedAt('Value');
        $fixture->setUser('Value');
        $fixture->setTeams('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'player[gamerTag]' => 'Something New',
            'player[mainGame]' => 'Something New',
            'player[stats]' => 'Something New',
            'player[bio]' => 'Something New',
            'player[deletedAt]' => 'Something New',
            'player[user]' => 'Something New',
            'player[teams]' => 'Something New',
        ]);

        self::assertResponseRedirects('/player/');

        $fixture = $this->playerRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getGamerTag());
        self::assertSame('Something New', $fixture[0]->getMainGame());
        self::assertSame('Something New', $fixture[0]->getStats());
        self::assertSame('Something New', $fixture[0]->getBio());
        self::assertSame('Something New', $fixture[0]->getDeletedAt());
        self::assertSame('Something New', $fixture[0]->getUser());
        self::assertSame('Something New', $fixture[0]->getTeams());
    }

    public function testRemove(): void
    {
        $this->markTestIncomplete();
        $fixture = new Player();
        $fixture->setGamerTag('Value');
        $fixture->setMainGame('Value');
        $fixture->setStats('Value');
        $fixture->setBio('Value');
        $fixture->setDeletedAt('Value');
        $fixture->setUser('Value');
        $fixture->setTeams('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/player/');
        self::assertSame(0, $this->playerRepository->count([]));
    }
}
