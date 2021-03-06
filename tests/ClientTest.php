<?php

namespace TheFox\Test;

use DateTime;
use PHPUnit\Framework\TestCase;
use Zend\Mail\Message;
use Zend\Mail\Storage;
use Zend\Mail\Headers;
use Zend\Mail\Header\Date;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use TheFox\Imap\Server;
use TheFox\Imap\Client;
use TheFox\Imap\Storage\DirectoryStorage;

class ClientTest extends TestCase
{
    public function testGetStatus1()
    {
        $client = new Client();
        $this->assertEquals(0, $client->getStatus('authStep'));

        $client->setStatus('authStep', 1);
        $this->assertEquals(1, $client->getStatus('authStep'));
    }

    public function testGetStatus2()
    {
        $client = new Client();
        $this->assertNull($client->getStatus('NOT_EXISTING'));
    }

    public function testSetIp()
    {
        $client = new Client();
        $client->setIp('192.168.241.21');
        $this->assertEquals('192.168.241.21', $client->getIp());
    }

    public function testGetIp()
    {
        $client = new Client();
        $this->assertEquals('', $client->getIp());
    }

    public function testSetPort()
    {
        $client = new Client();
        $client->setPort(1024);
        $this->assertEquals(1024, $client->getPort());
    }

    public function testGetPort()
    {
        $client = new Client();
        $this->assertEquals(0, $client->getPort());
    }

    public function testGetIpPort1()
    {
        $client = new Client();
        $client->setIp('192.168.241.21');
        $client->setPort(1024);
        $this->assertEquals('192.168.241.21:1024', $client->getIpPort());
    }

    public function testGetIpPort2()
    {
        $client = new Client();
        $client->setIpPort('192.168.241.21', 1024);
        $this->assertEquals('192.168.241.21:1024', $client->getIpPort());
    }

    /**
     * @return array
     */
    public function providerMsgGetArgs(): array
    {
        $rv = [];
        $expect = ['tag' => 'TAG1', 'command' => 'cmd2', 'args' => []];

        $expect['args'] = ['arg1'];
        $rv[] = ['TAG1 cmd2 "arg1"', $expect];
        $rv[] = ['TAG1 cmd2 "arg1" ', $expect];

        $expect['args'] = ['arg1', 'arg2'];
        $rv[] = ['TAG1 cmd2 "arg1" "arg2"', $expect];
        $rv[] = ['TAG1 cmd2 "arg1"  "arg2"', $expect];

        $expect['args'] = ['arg1', ''];
        $rv[] = ['TAG1 cmd2 "arg1" ""', $expect];

        $expect['args'] = ['arg1', '', 'arg3'];
        $rv[] = ['TAG1 cmd2 "arg1" "" arg3', $expect];

        $expect['args'] = ['arg1', 'arg2'];
        $rv[] = ['TAG1 cmd2 arg1 arg2', $expect];
        $rv[] = ['TAG1 cmd2 arg1  arg2', $expect];
        $rv[] = ['TAG1 cmd2  arg1 arg2', $expect];
        $rv[] = ['TAG1  cmd2 arg1 arg2', $expect];

        $expect['args'] = ['arg1', 'arg21 still arg22', 'aaarrg3'];
        $rv[] = ['TAG1 cmd2 arg1 "arg21 still arg22" aaarrg3', $expect];
        $rv[] = ['TAG1 cmd2 arg1 "arg21 still arg22"  aaarrg3', $expect];
        $rv[] = ['TAG1 cmd2 arg1 "arg21 still arg22"  aaarrg3', $expect];
        $rv[] = ['TAG1 cmd2 arg1 "arg21 still arg22"   aaarrg3', $expect];

        $expect['args'] = ['arg1', 'arg21 still arg22 ', 'aaarrg3'];
        $rv[] = ['TAG1 cmd2 arg1 "arg21 still arg22 " aaarrg3', $expect];
        $rv[] = ['TAG1 cmd2 arg1  "arg21 still arg22 " aaarrg3', $expect];

        $expect['args'] = ['arg1', 'arg21 still  arg22', 'aaarrg3'];
        $rv[] = ['TAG1 cmd2 arg1 "arg21 still  arg22" aaarrg3', $expect];
        $rv[] = ['TAG1 cmd2 arg1 "arg21 still  arg22"  aaarrg3', $expect];
        $rv[] = ['TAG1 cmd2 arg1 "arg21 still  arg22"   aaarrg3', $expect];

        $expect['args'] = ['arg1', ' arg21 still arg22', 'aaarrg3'];
        $rv[] = ['TAG1 cmd2 arg1 " arg21 still arg22" aaarrg3', $expect];

        $expect['args'] = ['arg1', ' arg21 still arg22 ', 'aaarrg3'];
        $rv[] = ['TAG1 cmd2 arg1 " arg21 still arg22 " aaarrg3', $expect];

        return $rv;
    }

    /**
     * @dataProvider providerMsgGetArgs
     * @param string $msgRaw
     * @param array $expect
     */
    public function testMsgGetArgs(string $msgRaw, array $expect)
    {
        $client = new Client();
        $this->assertEquals($expect, $client->getMessageArguments($msgRaw));
    }

    /**
     * @return array
     */
    public function providerMsgRawParenthesizedlist(): array
    {
        $rv = [
            ['', []],
            ['aaa', ['aaa']],
            ['()', []],
            ['(a)', ['a']],
            ['("a")', ['a']],
            ['(a b)', ['a', 'b']],
            ['(a "b")', ['a', 'b']],
            ['(a "bc def")', ['a', 'bc def']],
            ['(aa bbb "cccc")', ['aa', 'bbb', 'cccc']],
            ['(aa bbb "cccc dd")', ['aa', 'bbb', 'cccc dd']],
            ['((aa bbb "cccc") dd)', [['aa', 'bbb', 'cccc'], 'dd']],
            ['(aa bbb cccc) dd', [['aa', 'bbb', 'cccc'], 'dd']],
            ['aa (bbb cccc)', ['aa', ['bbb', 'cccc']]],
            ['(aa bbb "cccc") dd', [['aa', 'bbb', 'cccc'], 'dd']],
            ['aa (bbb "cccc") dd', ['aa', ['bbb', 'cccc'], 'dd']],
            ['(a bb ccc)', ['a', 'bb', 'ccc']],
            ['(a (bb) ccc)', ['a', ['bb'], 'ccc']],
            ['(a (bb ccc) dddd)', ['a', ['bb', 'ccc'], 'dddd']],
            ['(a (bb ccc dddd) eeeee)', ['a', ['bb', 'ccc', 'dddd'], 'eeeee']],
            ['(a ((bb ccc) dddd) eeeee)', ['a', [['bb', 'ccc'], 'dddd'], 'eeeee']],
            ['(a bb "ccc") dddd', [['a', 'bb', 'ccc'], 'dddd']],
            ['("ccc" a bb) dddd', [['ccc', 'a', 'bb'], 'dddd']],
            ['BEFORE X', ['BEFORE', 'X']],
            ['BEFORE 1990', ['BEFORE', '1990']],
        ];

        $raw = '(UID RFC822.SIZE FLAGS BODY.PEEK[HEADER.FIELDS (From To Cc Bcc Subject Date ';
        $raw .= 'Message-ID Priority X-Priority References Newsgroups In-Reply-To Content-Type Reply-To)])';

        $expect = [
            'UID',
            'RFC822.SIZE',
            'FLAGS',
            'BODY.PEEK',
            [
                'HEADER.FIELDS',
                [
                    'From',
                    'To',
                    'Cc',
                    'Bcc',
                    'Subject',
                    'Date',
                    'Message-ID',
                    'Priority',
                    'X-Priority',
                    'References',
                    'Newsgroups',
                    'In-Reply-To',
                    'Content-Type',
                    'Reply-To',
                ],
            ],
        ];
        $rv[] = [$raw, $expect];

        return $rv;
    }

    /**
     * @dataProvider providerMsgRawParenthesizedlist
     * @param string $msgRaw
     * @param array $expect
     */
    public function testMsgGetParenthesizedlist(string $msgRaw, array $expect)
    {
        $client = new Client();
        $this->assertEquals($expect, $client->getParenthesizedMessageList($msgRaw));
    }

    public function testCreateSequenceSet1()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);
        $client->setStatus('hasAuth', true);
        $server->addFolder('test_dir1');
        $client->handleRawPacket('6 select test_dir1');

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir1');

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir1');

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir1');

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir1');

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 5');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir1');

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 6');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir1');

        $seq = $client->createSequenceSet('0', false);
        $this->assertEquals([], $seq);

        $seq = $client->createSequenceSet('1', false);
        $this->assertEquals([1], $seq);

        $seq = $client->createSequenceSet('3', false);
        $this->assertEquals([3], $seq);

        $seq = $client->createSequenceSet('3,5', false);
        $this->assertEquals([3, 5], $seq);

        $seq = $client->createSequenceSet('3,5,6,4', false);
        $this->assertEquals([3, 4, 5, 6], $seq);

        $seq = $client->createSequenceSet('3, 5', false);
        $this->assertEquals([3, 5], $seq);

        $seq = $client->createSequenceSet('3:3', false);
        $this->assertEquals([3], $seq);

        $seq = $client->createSequenceSet('3:4', false);
        $this->assertEquals([3, 4], $seq);

        $seq = $client->createSequenceSet('3:5', false);
        $this->assertEquals([3, 4, 5], $seq);

        $seq = $client->createSequenceSet('5:3', false);
        $this->assertEquals([3, 4, 5], $seq);

        $seq = $client->createSequenceSet('3:5,2', false);
        $this->assertEquals([2, 3, 4, 5], $seq);

        $seq = $client->createSequenceSet('*', false);
        $this->assertEquals([1, 2, 3, 4, 5, 6], $seq);

        $seq = $client->createSequenceSet('3:*', false);
        $this->assertEquals([3, 4, 5, 6], $seq);

        $seq = $client->createSequenceSet('3:*,2', false);
        $this->assertEquals([2, 3, 4, 5, 6], $seq);

        $seq = $client->createSequenceSet('7', false);
        $this->assertEquals([], $seq);

        $seq = $client->createSequenceSet('8:9', false);
        $this->assertEquals([], $seq);

        $seq = $client->createSequenceSet('100001', true);
        $this->assertEquals([1], $seq);

        $seq = $client->createSequenceSet('100002', true);
        $this->assertEquals([2], $seq);

        $seq = $client->createSequenceSet('100002,100004', true);
        $this->assertEquals([2, 4], $seq);

        $seq = $client->createSequenceSet('100002, 100004', true);
        $this->assertEquals([2, 4], $seq);

        $seq = $client->createSequenceSet('100002,100005,100004,100003', true);
        $this->assertEquals([2, 3, 4, 5], $seq);

        $seq = $client->createSequenceSet('100002:100002', true);
        $this->assertEquals([2], $seq);

        $seq = $client->createSequenceSet('100002:100003', true);
        $this->assertEquals([2, 3], $seq);

        $seq = $client->createSequenceSet('100002:100004', true);
        $this->assertEquals([2, 3, 4], $seq);

        $seq = $client->createSequenceSet('100004:100002', true);
        $this->assertEquals([2, 3, 4], $seq);

        $seq = $client->createSequenceSet('100002:100004,100005', true);
        $this->assertEquals([2, 3, 4, 5], $seq);

        $seq = $client->createSequenceSet('*', true);
        $this->assertEquals([1, 2, 3, 4, 5, 6], $seq);

        $seq = $client->createSequenceSet('100002:*', true);
        $this->assertEquals([2, 3, 4, 5, 6], $seq);

        $seq = $client->createSequenceSet('100002:*,100001', true);
        $this->assertEquals([1, 2, 3, 4, 5, 6], $seq);

        $seq = $client->createSequenceSet('100007', true);
        $this->assertEquals([], $seq);

        $seq = $client->createSequenceSet('100007:100009', true);
        $this->assertEquals([], $seq);

        $seq = $client->createSequenceSet('999999:*', true);
        $this->assertEquals([6], $seq);

        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($storage1->getPath());
    }

    /**
     * @return array
     */
    public function providerMsgHandle(): array
    {
        $rv = [
            ['NO_COMMAND', 'NO_COMMAND BAD Not implemented: "NO_COMMAND" ""'],
            ['1 NO_COMMAND', '1 BAD Not implemented: "1" "NO_COMMAND"'],
        ];

        foreach ($rv as $cn => $case) {
            $rv[$cn][1] .= Client::MSG_SEPARATOR;
        }

        return $rv;
    }

    /**
     * @dataProvider providerMsgHandle
     * @param string $msgRaw
     * @param string $expect
     */
    public function testMsgHandleBasic(string $msgRaw, string $expect)
    {
        $server = new Server();

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket($msgRaw);

        $this->assertEquals($expect, $msg);

        $server->shutdown();
    }

    public function testMsgHandleCapability()
    {
        $server = new Server();

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket('1 capability');
        $expect = '* CAPABILITY IMAP4rev1 AUTH=PLAIN' . Client::MSG_SEPARATOR;
        $expect .= '1 OK CAPABILITY completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);
    }

    public function testMsgHandleNoop()
    {
        $server = new Server();

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket('2 NOOP');
        $this->assertEquals('2 OK NOOP completed client 1, ""' . Client::MSG_SEPARATOR, $msg);

        $server->shutdown();
    }

    public function testMsgHandleLogout()
    {
        $server = new Server();

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket('3 LOGOUT');
        $expect = '* BYE IMAP4rev1 Server logging out' . Client::MSG_SEPARATOR;
        $expect .= '3 OK LOGOUT completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);
    }

    public function testMsgHandleAuthenticate()
    {
        $server = new Server();

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket('4 authenticate UNSUPPORTED');
        $this->assertEquals('4 NO UNSUPPORTED Unsupported authentication mechanism' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('4 authenticate plain');
        $this->assertEquals('+' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('AHRoZWZveAB0ZXN0');
        $this->assertEquals('4 OK plain authentication successful' . Client::MSG_SEPARATOR, $msg);
    }

    public function testMsgHandleLogin()
    {
        $server = new Server();

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket('3 LOGIN');
        $this->assertEquals('3 BAD Arguments invalid.' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('3 LOGIN user');
        $this->assertEquals('3 BAD Arguments invalid.' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('3 LOGIN user password');
        $this->assertEquals('3 OK LOGIN completed' . Client::MSG_SEPARATOR, $msg);
    }

    public function testMsgHandleSelect1()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket('6 select test_dir');
        $this->assertEquals('6 NO select failure' . Client::MSG_SEPARATOR, $msg);

        $client->setStatus('hasAuth', true);
        $msg = $client->handleRawPacket('6 select');
        $this->assertEquals('6 BAD Arguments invalid.' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('6 select test_dir');
        $this->assertEquals('6 NO "test_dir" no such mailbox' . Client::MSG_SEPARATOR, $msg);

        $server->addFolder('test_dir');
        $msg = $client->handleRawPacket('6 select test_dir');

        $expect = '';
        $expect .= '* 0 EXISTS' . Client::MSG_SEPARATOR;
        $expect .= '* 0 RECENT' . Client::MSG_SEPARATOR;
        $expect .= '* OK [UNSEEN 0] Message 0 is first unseen' . Client::MSG_SEPARATOR;
        $expect .= '* OK [UIDNEXT 100001] Predicted next UID' . Client::MSG_SEPARATOR;
        $expect .= '* FLAGS (\Answered \Flagged \Deleted \Seen \Draft)' . Client::MSG_SEPARATOR;
        $expect .= '* OK [PERMANENTFLAGS (\Deleted \Seen \*)] Limited' . Client::MSG_SEPARATOR;
        $expect .= '6 OK [READ-WRITE] SELECT completed' . Client::MSG_SEPARATOR;

        $this->assertEquals($expect, $msg);
    }

    public function testMsgHandleSelect2()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);
        $server->addFolder('test_dir');

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);
        $client->setStatus('hasAuth', true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1a');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir', [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1b');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir', [Storage::FLAG_SEEN], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir', null, true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3a');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir', [], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3b');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir', [Storage::FLAG_SEEN], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir', null, false);

        $msg = $client->handleRawPacket('6 select test_dir');

        $expect = '';
        $expect .= '* 6 EXISTS' . Client::MSG_SEPARATOR;
        $expect .= '* 3 RECENT' . Client::MSG_SEPARATOR;
        $expect .= '* OK [UNSEEN 1] Message 1 is first unseen' . Client::MSG_SEPARATOR;
        $expect .= '* OK [UIDNEXT 100007] Predicted next UID' . Client::MSG_SEPARATOR;
        $expect .= '* FLAGS (\Answered \Flagged \Deleted \Seen \Draft)' . Client::MSG_SEPARATOR;
        $expect .= '* OK [PERMANENTFLAGS (\Deleted \Seen \*)] Limited' . Client::MSG_SEPARATOR;
        $expect .= '6 OK [READ-WRITE] SELECT completed' . Client::MSG_SEPARATOR;

        $this->assertEquals($expect, $msg);

        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($path1);
    }

    public function testMsgHandleSelect3()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);
        $server->addFolder('test_dir');

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);
        $client->setStatus('hasAuth', true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir', [Storage::FLAG_SEEN], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir', [Storage::FLAG_SEEN], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir', [], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir', [], false);

        $msg = $client->handleRawPacket('6 select test_dir');

        $expect = '';
        $expect .= '* 4 EXISTS' . Client::MSG_SEPARATOR;
        $expect .= '* 0 RECENT' . Client::MSG_SEPARATOR;
        $expect .= '* OK [UNSEEN 3] Message 3 is first unseen' . Client::MSG_SEPARATOR;
        $expect .= '* OK [UIDNEXT 100005] Predicted next UID' . Client::MSG_SEPARATOR;
        $expect .= '* FLAGS (\Answered \Flagged \Deleted \Seen \Draft)' . Client::MSG_SEPARATOR;
        $expect .= '* OK [PERMANENTFLAGS (\Deleted \Seen \*)] Limited' . Client::MSG_SEPARATOR;
        $expect .= '6 OK [READ-WRITE] SELECT completed' . Client::MSG_SEPARATOR;

        $this->assertEquals($expect, $msg);

        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($path1);
    }

    public function testMsgHandleSelect4()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);
        $server->addFolder('test_dir');

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);
        $client->setStatus('hasAuth', true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir', [Storage::FLAG_SEEN], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir', [Storage::FLAG_SEEN], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir', [Storage::FLAG_SEEN], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir', [], true);

        $msg = $client->handleRawPacket('6 select test_dir');

        $expect = '';
        $expect .= '* 4 EXISTS' . Client::MSG_SEPARATOR;
        $expect .= '* 1 RECENT' . Client::MSG_SEPARATOR;
        $expect .= '* OK [UNSEEN 4] Message 4 is first unseen' . Client::MSG_SEPARATOR;
        $expect .= '* OK [UIDNEXT 100005] Predicted next UID' . Client::MSG_SEPARATOR;
        $expect .= '* FLAGS (\Answered \Flagged \Deleted \Seen \Draft)' . Client::MSG_SEPARATOR;
        $expect .= '* OK [PERMANENTFLAGS (\Deleted \Seen \*)] Limited' . Client::MSG_SEPARATOR;
        $expect .= '6 OK [READ-WRITE] SELECT completed' . Client::MSG_SEPARATOR;

        $this->assertEquals($expect, $msg);

        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($path1);
    }

    public function testMsgHandleSelect5()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);
        $server->addFolder('test_dir');

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);
        $client->setStatus('hasAuth', true);

        $msg = $client->handleRawPacket('6 select Inbox');

        $expect = '';
        $expect .= '* 0 EXISTS' . Client::MSG_SEPARATOR;
        $expect .= '* 0 RECENT' . Client::MSG_SEPARATOR;
        $expect .= '* OK [UNSEEN 0] Message 0 is first unseen' . Client::MSG_SEPARATOR;
        $expect .= '* OK [UIDNEXT 100001] Predicted next UID' . Client::MSG_SEPARATOR;
        $expect .= '* FLAGS (\Answered \Flagged \Deleted \Seen \Draft)' . Client::MSG_SEPARATOR;
        $expect .= '* OK [PERMANENTFLAGS (\Deleted \Seen \*)] Limited' . Client::MSG_SEPARATOR;
        $expect .= '6 OK [READ-WRITE] SELECT completed' . Client::MSG_SEPARATOR;

        $this->assertEquals($expect, $msg);

        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($path1);
    }

    public function testMsgHandleCreate()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket('7 create');
        $this->assertEquals('7 NO create failure' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('7 create test_dir');
        $this->assertEquals('7 NO create failure' . Client::MSG_SEPARATOR, $msg);

        $client->setStatus('hasAuth', true);
        $msg = $client->handleRawPacket('7 create test_dir');
        $this->assertEquals('7 OK CREATE completed' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('7 create test_dir');
        $this->assertEquals('7 NO CREATE failure: folder already exists' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('7 create');
        $this->assertEquals('7 BAD Arguments invalid.' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('7 create test_dir/test_subdir1');
        $expect = '7 NO CREATE failure: invalid name';
        $expect .= ' - no directory separator allowed in folder name' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('7 create test_dir.test_subdir2');
        $this->assertEquals('7 OK CREATE completed' . Client::MSG_SEPARATOR, $msg);
    }

    public function testMsgHandleSubscribe()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket('8 subscribe');
        $this->assertEquals('8 NO subscribe failure' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('8 subscribe test_dir');
        $this->assertEquals('8 NO subscribe failure' . Client::MSG_SEPARATOR, $msg);

        $client->setStatus('hasAuth', true);

        $msg = $client->handleRawPacket('8 subscribe');
        $this->assertEquals('8 BAD Arguments invalid.' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('8 subscribe test_dir');
        $this->assertEquals('8 NO SUBSCRIBE failure: no subfolder named test_dir' . Client::MSG_SEPARATOR, $msg);

        $server->addFolder('test_dir');
        $msg = $client->handleRawPacket('8 subscribe test_dir');
        $this->assertEquals('8 OK SUBSCRIBE completed' . Client::MSG_SEPARATOR, $msg);
    }

    public function testMsgHandleUnsubscribe()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket('9 unsubscribe test_dir');
        $this->assertEquals('9 NO unsubscribe failure' . Client::MSG_SEPARATOR, $msg);

        $client->setStatus('hasAuth', true);

        $msg = $client->handleRawPacket('9 unsubscribe');
        $this->assertEquals('9 BAD Arguments invalid.' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('9 unsubscribe test_dir');
        $this->assertEquals('9 NO UNSUBSCRIBE failure: no subfolder named test_dir' . Client::MSG_SEPARATOR, $msg);

        $server->addFolder('test_dir');
        $msg = $client->handleRawPacket('9 unsubscribe test_dir');
        $this->assertEquals('9 OK UNSUBSCRIBE completed' . Client::MSG_SEPARATOR, $msg);
    }

    public function testMsgHandleList()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket('10 LIST');
        $this->assertEquals('10 NO list failure' . Client::MSG_SEPARATOR, $msg);

        $client->setStatus('hasAuth', true);

        $msg = $client->handleRawPacket('10 LIST');
        $this->assertEquals('10 BAD Arguments invalid.' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('10 LIST test_dir1');
        $this->assertEquals('10 BAD Arguments invalid.' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('10 LIST test_dir1.*');
        $this->assertEquals('10 BAD Arguments invalid.' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('10 LIST "" test_dir1');
        $this->assertEquals('10 OK LIST completed' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('10 LIST "" test_dir1.*');
        $this->assertEquals('10 OK LIST completed' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('10 LIST "" INBOX');
        $expect = '* LIST () "." "INBOX"' . Client::MSG_SEPARATOR;
        $expect .= '10 OK LIST completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $server->addFolder('test_dir1');

        $msg = $client->handleRawPacket('10 LIST "" test_dir1');
        $expect = '* LIST () "." "test_dir1"' . Client::MSG_SEPARATOR;
        $expect .= '10 OK LIST completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('10 LIST "" test_dir1.*');
        $this->assertEquals('10 OK LIST completed' . Client::MSG_SEPARATOR, $msg);

        $server->addFolder('test_dir1.test_subdir2');

        #$msg = $client->msgHandle('10 LIST "" test_dir1.*');
        #$expect = '* LIST () "." "test_dir1.test_subdir2"'.Client::MSG_SEPARATOR;
        #$expect .= '10 OK LIST completed'.Client::MSG_SEPARATOR;
        #$this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('10 LIST "test_dir1" test_sub*');
        $expect = '* LIST () "." "test_dir1.test_subdir2"' . Client::MSG_SEPARATOR;
        $expect .= '10 OK LIST completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);
    }

    public function testMsgHandleLsub()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket('11 lsub');
        $this->assertEquals('11 NO lsub failure' . Client::MSG_SEPARATOR, $msg);

        $client->setStatus('hasAuth', true);

        $msg = $client->handleRawPacket('11 lsub');
        $this->assertEquals('11 BAD Arguments invalid.' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('11 lsub test_dir');
        $this->assertEquals('11 OK LSUB completed' . Client::MSG_SEPARATOR, $msg);

        $server->addFolder('test_dir1');

        $client->handleRawPacket('8 subscribe test_dir1');
        $msg = $client->handleRawPacket('11 lsub test_dir1');
        $expect = '* LSUB () "." "test_dir1"' . Client::MSG_SEPARATOR . '11 OK LSUB completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $server->addFolder('test_dir2');
        $client->handleRawPacket('8 subscribe test_dir2');
        $msg = $client->handleRawPacket('11 lsub test_dir2');
        $expect = '* LSUB () "." "test_dir1"' . Client::MSG_SEPARATOR;
        $expect .= '* LSUB () "." "test_dir2"' . Client::MSG_SEPARATOR;
        $expect .= '11 OK LSUB completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);
    }

    public function testMsgHandleAppend1()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $this->assertEquals(0, $client->getStatus('appendStep'));

        $msg = $client->handleRawPacket('19 append');
        $this->assertEquals('19 NO append failure' . Client::MSG_SEPARATOR, $msg);
        $this->assertEquals(0, $client->getStatus('appendStep'));

        $client->setStatus('hasAuth', true);

        $msg = $client->handleRawPacket('19 append');
        $this->assertEquals('19 BAD Arguments invalid.' . Client::MSG_SEPARATOR, $msg);
        $this->assertEquals(0, $client->getStatus('appendStep'));

        $msg = $client->handleRawPacket('19 append "Sent"');
        $this->assertEquals('19 BAD Arguments invalid.' . Client::MSG_SEPARATOR, $msg);
        $this->assertEquals(0, $client->getStatus('appendStep'));

        $msg = $client->handleRawPacket('19 append "Sent" (\Seen)');
        $this->assertEquals('19 BAD Arguments invalid.' . Client::MSG_SEPARATOR, $msg);
        $this->assertEquals(0, $client->getStatus('appendStep'));
    }

    public function testMsgHandleAppend2()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $server->addFolder('Sent');

        $client->setStatus('hasAuth', true);

        $this->assertEquals(0, $client->getStatus('appendStep'));

        $msg = $client->handleRawPacket('19 append "Sent" (' . Storage::FLAG_SEEN . ') {416}');
        $this->assertEquals('+ Ready for literal data' . Client::MSG_SEPARATOR, $msg);
        $this->assertEquals(2, $client->getStatus('appendStep'));

        $msg = $client->handleRawPacket('Message-ID: <53E79E0C.7060001@fox21.at>');
        $this->assertEquals(null, $msg);

        $msg = $client->handleRawPacket('Date: Sun, 10 Aug 2014 18:30:04 +0200');
        $msg = $client->handleRawPacket('From: Derp Dev <dev1@fox21.at>');
        $raw = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:31.0) Gecko/20100101 Thunderbird/31.0';
        $msg = $client->handleRawPacket($raw);
        $msg = $client->handleRawPacket('MIME-Version: 1.0');
        $msg = $client->handleRawPacket('To: user_560d <2985d252-0065-4a51-b0b0-96f37af6275d@phpchat.fox21.at>');
        $msg = $client->handleRawPacket('Subject: test2a');
        $msg = $client->handleRawPacket('Content-Type: text/plain; charset=utf-8; format=flowed');
        $msg = $client->handleRawPacket('Content-Transfer-Encoding: 7bit');
        $msg = $client->handleRawPacket('');
        $msg = $client->handleRawPacket('test2');

        $this->assertEquals('19 OK APPEND completed' . Client::MSG_SEPARATOR, $msg);
        $this->assertEquals(3, $client->getStatus('appendStep'));

        $finder = new Finder();
        $files = $finder->in($path1 . '/Sent')->files();
        $this->assertEquals(1, count($files));

        $msgIds = $server->getMailIdsByFlags([Storage::FLAG_SEEN]);
        $this->assertEquals(1, count($msgIds));

        $msg = $client->handleRawPacket('19 append "Sent" (' . Storage::FLAG_ANSWERED . ') {414}');
        $this->assertEquals('+ Ready for literal data' . Client::MSG_SEPARATOR, $msg);
        $this->assertEquals(2, $client->getStatus('appendStep'));

        $msg = $client->handleRawPacket('Message-ID: <53E79E0C.7060001@fox21.at>');
        $this->assertEquals(null, $msg);

        $msg = $client->handleRawPacket('Date: Sun, 10 Aug 2014 18:30:04 +0200');
        $msg = $client->handleRawPacket('From: Derp Dev <dev1@fox21.at>');
        $raw = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:31.0) Gecko/20100101 Thunderbird/31.0';
        $msg = $client->handleRawPacket($raw);
        $msg = $client->handleRawPacket('MIME-Version: 1.0');
        $msg = $client->handleRawPacket('To: user_560d <2985d252-0065-4a51-b0b0-96f37af6275d@phpchat.fox21.at>');
        $msg = $client->handleRawPacket('Subject: test2b');
        $msg = $client->handleRawPacket('Content-Type: text/plain; charset=utf-8; format=flowed');
        $msg = $client->handleRawPacket('Content-Transfer-Encoding: 7bit');
        $msg = $client->handleRawPacket('');
        $msg = $client->handleRawPacket('tes');

        $this->assertEquals('19 OK APPEND completed' . Client::MSG_SEPARATOR, $msg);
        $this->assertEquals(3, $client->getStatus('appendStep'));

        $finder = new Finder();
        $files = $finder->in($path1 . '/Sent')->files();
        $this->assertEquals(2, count($files));

        $msgIds = $server->getMailIdsByFlags([Storage::FLAG_ANSWERED]);
        $this->assertEquals(1, count($msgIds));

        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($path1);
    }

    public function testMsgHandleAppend3()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $server->addFolder('Sent');

        $client->setStatus('hasAuth', true);

        $this->assertEquals(0, $client->getStatus('appendStep'));

        $msg = $client->handleRawPacket('19 append "Sent" {417}');

        $this->assertEquals('+ Ready for literal data' . Client::MSG_SEPARATOR, $msg);
        $this->assertEquals(2, $client->getStatus('appendStep'));

        $msg = $client->handleRawPacket('Message-ID: <53E79E0C.7060001@fox21.at>');
        $this->assertEquals(null, $msg);

        $msg = $client->handleRawPacket('Date: Sun, 10 Aug 2014 18:30:04 +0200');
        $msg = $client->handleRawPacket('From: Derp Dev <dev1@fox21.at>');
        $raw = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:31.0) Gecko/20100101 Thunderbird/31.0';
        $msg = $client->handleRawPacket($raw);
        $msg = $client->handleRawPacket('MIME-Version: 1.0');
        $msg = $client->handleRawPacket('To: user_560d <2985d252-0065-4a51-b0b0-96f37af6275d@phpchat.fox21.at>');
        $msg = $client->handleRawPacket('Subject: test3');
        $msg = $client->handleRawPacket('Content-Type: text/plain; charset=utf-8; format=flowed');
        $msg = $client->handleRawPacket('Content-Transfer-Encoding: 7bit');
        $msg = $client->handleRawPacket('');
        $msg = $client->handleRawPacket('test333');

        $this->assertEquals('19 OK APPEND completed' . Client::MSG_SEPARATOR, $msg);
        $this->assertEquals(3, $client->getStatus('appendStep'));

        $finder = new Finder();
        $files = $finder->in($path1 . '/Sent')->files();
        $this->assertEquals(1, count($files));
    }

    public function testMsgHandleAppend4()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $server->addFolder('Sent');

        $client->setStatus('hasAuth', true);

        $this->assertEquals(0, $client->getStatus('appendStep'));

        $msg = $client->handleRawPacket('19 append "Sent" 2014-11-18 {417}');

        $this->assertEquals('+ Ready for literal data' . Client::MSG_SEPARATOR, $msg);
        $this->assertEquals(2, $client->getStatus('appendStep'));

        $msg = $client->handleRawPacket('Message-ID: <53E79E0C.7060001@fox21.at>');
        $this->assertEquals(null, $msg);

        $msg = $client->handleRawPacket('Date: Sun, 10 Aug 2014 18:30:04 +0200');
        $msg = $client->handleRawPacket('From: Derp Dev <dev1@fox21.at>');
        $raw = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:31.0) Gecko/20100101 Thunderbird/31.0';
        $msg = $client->handleRawPacket($raw);
        $msg = $client->handleRawPacket('MIME-Version: 1.0');
        $msg = $client->handleRawPacket('To: user_560d <2985d252-0065-4a51-b0b0-96f37af6275d@phpchat.fox21.at>');
        $msg = $client->handleRawPacket('Subject: test3');
        $msg = $client->handleRawPacket('Content-Type: text/plain; charset=utf-8; format=flowed');
        $msg = $client->handleRawPacket('Content-Transfer-Encoding: 7bit');
        $msg = $client->handleRawPacket('');
        $msg = $client->handleRawPacket('test333');

        $this->assertEquals('19 OK APPEND completed' . Client::MSG_SEPARATOR, $msg);
        $this->assertEquals(3, $client->getStatus('appendStep'));

        $finder = new Finder();
        $files = $finder->in($path1 . '/Sent')->files();
        $this->assertEquals(1, count($files));
    }

    public function testMsgHandleAppend5()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $server->addFolder('Sent');

        $client->setStatus('hasAuth', true);

        $this->assertEquals(0, $client->getStatus('appendStep'));

        $msg = $client->handleRawPacket('19 append "Sent" 2014-11-18 (' . Storage::FLAG_ANSWERED . ') {417}');

        $this->assertEquals('+ Ready for literal data' . Client::MSG_SEPARATOR, $msg);
        $this->assertEquals(2, $client->getStatus('appendStep'));

        $msg = $client->handleRawPacket('Message-ID: <53E79E0C.7060001@fox21.at>');
        $this->assertEquals(null, $msg);

        $msg = $client->handleRawPacket('Date: Sun, 10 Aug 2014 18:30:04 +0200');
        $msg = $client->handleRawPacket('From: Derp Dev <dev1@fox21.at>');
        $raw = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:31.0) Gecko/20100101 Thunderbird/31.0';
        $msg = $client->handleRawPacket($raw);
        $msg = $client->handleRawPacket('MIME-Version: 1.0');
        $msg = $client->handleRawPacket('To: user_560d <2985d252-0065-4a51-b0b0-96f37af6275d@phpchat.fox21.at>');
        $msg = $client->handleRawPacket('Subject: test3');
        $msg = $client->handleRawPacket('Content-Type: text/plain; charset=utf-8; format=flowed');
        $msg = $client->handleRawPacket('Content-Transfer-Encoding: 7bit');
        $msg = $client->handleRawPacket('');
        $msg = $client->handleRawPacket('test333');

        $this->assertEquals('19 OK APPEND completed' . Client::MSG_SEPARATOR, $msg);
        $this->assertEquals(3, $client->getStatus('appendStep'));

        $finder = new Finder();
        $files = $finder->in($path1 . '/Sent')->files();
        $this->assertEquals(1, count($files));
    }

    public function testMsgHandleAppend6()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $client->setStatus('hasAuth', true);

        $this->assertEquals(0, $client->getStatus('appendStep'));

        $msg = $client->handleRawPacket('19 append "Sent" 2014-11-18 (' . Storage::FLAG_ANSWERED . ') {417}');

        $this->assertEquals('+ Ready for literal data' . Client::MSG_SEPARATOR, $msg);
        $this->assertEquals(2, $client->getStatus('appendStep'));

        $msg = $client->handleRawPacket('Message-ID: <53E79E0C.7060001@fox21.at>');
        $this->assertEquals(null, $msg);

        $msg = $client->handleRawPacket('Date: Sun, 10 Aug 2014 18:30:04 +0200');
        $msg = $client->handleRawPacket('From: Derp Dev <dev1@fox21.at>');
        $raw = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:31.0) Gecko/20100101 Thunderbird/31.0';
        $msg = $client->handleRawPacket($raw);
        $msg = $client->handleRawPacket('MIME-Version: 1.0');
        $msg = $client->handleRawPacket('To: user_560d <2985d252-0065-4a51-b0b0-96f37af6275d@phpchat.fox21.at>');
        $msg = $client->handleRawPacket('Subject: test3');
        $msg = $client->handleRawPacket('Content-Type: text/plain; charset=utf-8; format=flowed');
        $msg = $client->handleRawPacket('Content-Transfer-Encoding: 7bit');
        $msg = $client->handleRawPacket('');
        $msg = $client->handleRawPacket('test333');

        $this->assertEquals('19 NO [TRYCREATE] Can not get folder: Sent' . Client::MSG_SEPARATOR, $msg);
        $this->assertEquals(3, $client->getStatus('appendStep'));
    }

    public function testMsgHandleCheck()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket('12 check');
        $this->assertEquals('12 NO check failure' . Client::MSG_SEPARATOR, $msg);

        $client->setStatus('hasAuth', true);

        $msg = $client->handleRawPacket('12 check');
        $this->assertEquals('12 NO No mailbox selected.' . Client::MSG_SEPARATOR, $msg);

        $server->addFolder('test_dir');
        $client->handleRawPacket('6 select test_dir');

        $msg = $client->handleRawPacket('12 check');
        $this->assertEquals('12 OK CHECK completed' . Client::MSG_SEPARATOR, $msg);
    }

    public function testMsgHandleClose()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket('13 close');
        $this->assertEquals('13 NO close failure' . Client::MSG_SEPARATOR, $msg);

        $client->setStatus('hasAuth', true);

        $msg = $client->handleRawPacket('13 close');
        $this->assertEquals('13 NO No mailbox selected.' . Client::MSG_SEPARATOR, $msg);

        $server->addFolder('test_dir');
        $client->handleRawPacket('6 select test_dir');

        $msg = $client->handleRawPacket('13 close');
        $this->assertEquals('13 OK CLOSE completed' . Client::MSG_SEPARATOR, $msg);
    }

    public function testMsgHandleExpunge1()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket('14 expunge');
        $this->assertEquals('14 NO expunge failure' . Client::MSG_SEPARATOR, $msg);

        $client->setStatus('hasAuth', true);

        $msg = $client->handleRawPacket('14 expunge');
        $this->assertEquals('14 NO No mailbox selected.' . Client::MSG_SEPARATOR, $msg);

        $server->addFolder('test_dir');
        $client->handleRawPacket('6 select test_dir');

        $msg = $client->handleRawPacket('14 expunge');
        $this->assertEquals('14 OK EXPUNGE completed' . Client::MSG_SEPARATOR, $msg);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir', [Storage::FLAG_DELETED]);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir');

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir', [Storage::FLAG_DELETED]);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir');

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 5');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir');

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 6');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir', [Storage::FLAG_DELETED]);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 7');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir', [Storage::FLAG_DELETED]);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 8');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir');

        $msg = $client->handleRawPacket('14 expunge');
        $expect = '* 1 EXPUNGE' . Client::MSG_SEPARATOR;
        $expect .= '* 2 EXPUNGE' . Client::MSG_SEPARATOR;
        $expect .= '* 4 EXPUNGE' . Client::MSG_SEPARATOR;
        $expect .= '* 4 EXPUNGE' . Client::MSG_SEPARATOR;
        $expect .= '14 OK EXPUNGE completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($path1);
    }

    public function testMsgHandleExpunge2()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket('14 expunge');
        $this->assertEquals('14 NO expunge failure' . Client::MSG_SEPARATOR, $msg);

        $client->setStatus('hasAuth', true);

        $msg = $client->handleRawPacket('14 expunge');
        $this->assertEquals('14 NO No mailbox selected.' . Client::MSG_SEPARATOR, $msg);

        $server->addFolder('test_dir');
        $client->handleRawPacket('6 select test_dir');

        $msg = $client->handleRawPacket('14 expunge');
        $this->assertEquals('14 OK EXPUNGE completed' . Client::MSG_SEPARATOR, $msg);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir');

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir', [Storage::FLAG_DELETED]);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir', [Storage::FLAG_DELETED]);

        $msg = $client->handleRawPacket('14 expunge');
        $expect = '* 2 EXPUNGE' . Client::MSG_SEPARATOR;
        $expect .= '* 2 EXPUNGE' . Client::MSG_SEPARATOR;
        $expect .= '14 OK EXPUNGE completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($path1);
    }

    /**
     * @return array
     */
    public function providerParseSearchKeys(): array
    {
        $rv = [];

        $rv[] = [[], []];
        $rv[] = [[''], ['']];
        $rv[] = [['1'], ['1']];
        $rv[] = [['ALL'], ['ALL']];
        $rv[] = [['ANSWERED'], ['ANSWERED']];
        $rv[] = [['BCC', 'thefox'], ['BCC thefox']];
        $rv[] = [['BEFORE', '1987-02-21'], ['BEFORE 1987-02-21']];
        $rv[] = [['BODY', 'fox'], ['BODY fox']];
        $rv[] = [['CC', 'fox'], ['CC fox']];
        $rv[] = [['DELETED'], ['DELETED']];
        $rv[] = [['DRAFT'], ['DRAFT']];
        $rv[] = [['FLAGGED'], ['FLAGGED']];
        $rv[] = [['FROM', 'fox'], ['FROM fox']];
        $rv[] = [['HEADER', 'FieldName1', 'fox'], ['HEADER FieldName1 fox']];
        $rv[] = [['KEYWORD', 'flag21'], ['KEYWORD flag21']];
        $rv[] = [['LARGER', 21], ['LARGER 21']];
        $rv[] = [['LARGER', '24'], ['LARGER 24']];
        $rv[] = [['NEW'], ['NEW']];
        $rv[] = [['NOT', 'BCC', 'fox'], ['NOT', 'BCC fox']];
        $rv[] = [['OLD'], ['OLD']];
        $rv[] = [['ON', '1987-02-21'], ['ON 1987-02-21']];
        $rv[] = [['OR', 'BCC', 'thefox', 'BCC', '21'], [['BCC thefox', 'OR', 'BCC 21']]];
        $rv[] = [['RECENT'], ['RECENT']];
        $rv[] = [['SEEN'], ['SEEN']];
        $rv[] = [['SENTBEFORE', '1987-02-21'], ['SENTBEFORE 1987-02-21']];
        $rv[] = [['SENTON', '1987-02-21'], ['SENTON 1987-02-21']];
        $rv[] = [['SENTSINCE', '1987-02-21'], ['SENTSINCE 1987-02-21']];
        $rv[] = [['SMALLER', 21], ['SMALLER 21']];
        $rv[] = [['SMALLER', '24'], ['SMALLER 24']];
        $rv[] = [['SUBJECT', 'hello'], ['SUBJECT hello']];
        $rv[] = [['SUBJECT', '"hello world"'], ['SUBJECT "hello world"']];
        $rv[] = [['TEXT', 'fox'], ['TEXT fox']];
        $rv[] = [['TO', 'fox'], ['TO fox']];
        $rv[] = [['UID', '100001'], ['UID 100001']];
        $rv[] = [['UNANSWERED'], ['UNANSWERED']];
        $rv[] = [['UNDELETED'], ['UNDELETED']];
        $rv[] = [['UNDRAFT'], ['UNDRAFT']];
        $rv[] = [['UNFLAGGED'], ['UNFLAGGED']];
        $rv[] = [['UNKEYWORD', 'flag21'], ['UNKEYWORD flag21']];
        $rv[] = [['UNSEEN'], ['UNSEEN']];

        $rv[] = [['1', '2'], ['1', 'AND', '2']];
        $rv[] = [['BCC', 'thefox', 'BCC', '21'], ['BCC thefox', 'AND', 'BCC 21']];
        $rv[] = [['BCC', 'thefox', 'AND', 'BCC', '21'], ['BCC thefox', 'AND', 'BCC 21']];

        $expect = ['BCC', 'at', 'OR', 'BCC', 'thefox', 'BCC', '21'];
        $rv[] = [$expect, ['BCC at', 'AND', ['BCC thefox', 'OR', 'BCC 21']]];

        $expect = ['OR', 'BCC', 'thefox', 'BCC', '21', 'AND', 'BCC', 'at'];
        $rv[] = [$expect, [['BCC thefox', 'OR', 'BCC 21'], 'AND', 'BCC at']];

        $expect = ['OR', 'SEEN', 'UNFLAGGED', 'AND', 'BCC', 'at'];
        $rv[] = [$expect, [['SEEN', 'OR', 'UNFLAGGED'], 'AND', 'BCC at']];

        $rv[] = [['BCC', 'thefox', 'NOT', 'BCC', '21'], ['BCC thefox', 'AND', 'NOT', 'BCC 21']];

        #return $rv;

        $rv[] = [
            [
                'ALL',
                'ANSWERED',
                'BCC',
                'thefox',
                'BEFORE',
                '1987-02-21',
                'BODY',
                'fox',
                'CC',
                'fox',
                'DELETED',
                'DRAFT',
                'FLAGGED',
                'FROM',
                'fox',
                'HEADER',
                'FieldName1',
                'fox',
                'KEYWORD',
                'flag21',
                'LARGER',
                21,
                'LARGER',
                '24',
                'NEW',
                'NOT',
                'BCC',
                'fox',
                'OLD',
                'ON',
                '1987-02-21',
                'OR',
                'BCC',
                'thefox',
                'BCC',
                '21',
                'RECENT',
                'SEEN',
                'SENTBEFORE',
                '1987-02-21',
                'SENTON',
                '1987-02-21',
                'SENTSINCE',
                '1987-02-21',
                'SMALLER',
                21,
                'SMALLER',
                '24',
                'SUBJECT',
                'hello',
                'SUBJECT',
                '"hello world"',
                'TEXT',
                'fox',
                'TO',
                'fox',
                'UID',
                '100001',
                'UNANSWERED',
                'UNDELETED',
                'UNDRAFT',
                'UNFLAGGED',
                'UNKEYWORD',
                'flag21',
                'UNSEEN',
            ],
            [
                'ALL',
                'AND',
                'ANSWERED',
                'AND',
                'BCC thefox',
                'AND',
                'BEFORE 1987-02-21',
                'AND',
                'BODY fox',
                'AND',
                'CC fox',
                'AND',
                'DELETED',
                'AND',
                'DRAFT',
                'AND',
                'FLAGGED',
                'AND',
                'FROM fox',
                'AND',
                'HEADER FieldName1 fox',
                'AND',
                'KEYWORD flag21',
                'AND',
                'LARGER 21',
                'AND',
                'LARGER 24',
                'AND',
                'NEW',
                'AND',
                'NOT',
                'BCC fox',
                'AND',
                'OLD',
                'AND',
                'ON 1987-02-21',
                'AND',
                ['BCC thefox', 'OR', 'BCC 21'],
                'AND',
                'RECENT',
                'AND',
                'SEEN',
                'AND',
                'SENTBEFORE 1987-02-21',
                'AND',
                'SENTON 1987-02-21',
                'AND',
                'SENTSINCE 1987-02-21',
                'AND',
                'SMALLER 21',
                'AND',
                'SMALLER 24',
                'AND',
                'SUBJECT hello',
                'AND',
                'SUBJECT "hello world"',
                'AND',
                'TEXT fox',
                'AND',
                'TO fox',
                'AND',
                'UID 100001',
                'AND',
                'UNANSWERED',
                'AND',
                'UNDELETED',
                'AND',
                'UNDRAFT',
                'AND',
                'UNFLAGGED',
                'AND',
                'UNKEYWORD flag21',
                'AND',
                'UNSEEN',
            ],
        ];

        $rv[] = [[['1', '2'], '3'], [['1', 'AND', '2'], 'AND', '3']];
        $rv[] = [['4', ['1', '2'], '3'], ['4', 'AND', ['1', 'AND', '2'], 'AND', '3']];
        $rv[] = [[['1', '2'], 'AND', '3'], [['1', 'AND', '2'], 'AND', '3']];

        $rv[] = [['OR', '1', '2'], [['1', 'OR', '2']]];
        $rv[] = [['OR', 'OR', '1', '2', '3'], [[['1', 'OR', '2'], 'OR', '3']]];

        $expect = ['OR', 'OR', 'BCC', 'thefox', 'TO', 'thefox', 'CC', 'thefox'];
        $rv[] = [$expect, [[['BCC thefox', 'OR', 'TO thefox'], 'OR', 'CC thefox']]];

        $rv[] = [['OR', ['1', '2'], '3'], [[['1', 'AND', '2'], 'OR', '3']]];

        $expect = ['123', 'OR', ['1', '2'], '3'];
        $rv[] = [$expect, ['123', 'AND', [['1', 'AND', '2'], 'OR', '3']]];

        return $rv;
    }

    /**
     * @dataProvider providerParseSearchKeys
     * @param array $testData
     * @param array $expect
     */
    public function testParseSearchKeys(array $testData, array $expect)
    {
        $client = new Client();
        $client->setId(1);

        $posOffset = 0;
        $rv = $client->parseSearchKeys($testData, $posOffset);
        $this->assertEquals($expect, $rv);
    }

    /**
     * @group medium
     */
    public function testMsgHandleUidSearch()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket('17 uid search');
        $this->assertEquals('17 NO uid failure' . Client::MSG_SEPARATOR, $msg);

        $client->setStatus('hasAuth', true);

        $msg = $client->handleRawPacket('17 uid search');
        $this->assertEquals('17 NO No mailbox selected.' . Client::MSG_SEPARATOR, $msg);

        $client->handleRawPacket('6 select INBOX');

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $server->addMail($message, null, null, false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $server->addMail($message, null, [Storage::FLAG_ANSWERED], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->addBcc('steve@apple.com');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $server->addMail($message, null, null, false);

        $headers = new Headers();
        $headers->addHeader(Date::fromString('Date: ' . date('r', mktime(0, 0, 0, 2, 21, 1987))));

        $message = new Message();
        $message->setHeaders($headers);
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $server->addMail($message, null, null, false);

        $message = new Message();
        $message->setHeaders($headers);
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 5');
        $message->setBody('my_body');
        $server->addMail($message, null, null, false);

        $headers = new Headers();
        $headers->addHeader(Date::fromString('Date: ' . date('r', mktime(0, 0, 0, 11, 20, 1986))));

        $message = new Message();
        $message->setHeaders($headers);
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 6');
        $message->setBody('hello world');
        $server->addMail($message, null, null, false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 7');
        $message->setBody('my_body');
        $server->addMail($message, null, [Storage::FLAG_DELETED], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 8');
        $message->setBody('my_body');
        $server->addMail($message, null, [Storage::FLAG_DRAFT], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 9');
        $message->setBody('my_body');
        $server->addMail($message, null, [Storage::FLAG_FLAGGED], false);

        $message = new Message();
        $message->addFrom('test@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 10');
        $message->setBody('my_body');
        $server->addMail($message, null, null, false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 11');
        $message->setBody('my super fancy long body for testing the size');
        $server->addMail($message, null, null, false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 12');
        $message->setBody('my_body');
        $server->addMail($message, null, [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 13');
        $message->setBody('my_body');
        $server->addMail($message, null, [], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        #$message->setSubject('my_subject 14');
        $message->setBody('my_body');
        $server->addMail($message, null, [], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 15');
        $message->setBody('this is a test body');
        $server->addMail($message, null, [], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 16');
        #$message->setBody('my_body');
        $server->addMail($message, null, [], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('steve@apple.com');
        $message->setSubject('my_subject 17');
        $message->setBody('my_body');
        $server->addMail($message, null, [], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 18');
        $message->setBody('my_body');
        $server->addMail($message, null, [], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 19');
        $message->setBody('my_body');
        $server->addMail($message, null, [Storage::FLAG_ANSWERED], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 20');
        $message->setBody('my_body');
        $server->addMail($message, null, [Storage::FLAG_DELETED], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 21');
        $message->setBody('my_body');
        $server->addMail($message, null, [Storage::FLAG_DRAFT], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->addCc('dev3@fox21.at');
        $message->setSubject('my_subject 22');
        $message->setBody('my_body');
        $server->addMail($message, null, [Storage::FLAG_FLAGGED], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->addCc('dev3@fox21.at');
        $message->setSubject('my_subject 23');
        $message->setBody('my_body');
        $server->addMail($message, null, [Storage::FLAG_SEEN], false);

        $msg = $client->handleRawPacket('17 uid SEARCH');
        $expect = '17 BAD Arguments invalid.' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH ALL');
        $expect = '* SEARCH 100001 100002 100003 100004 100005 100006 100007 100008 100009 100010 ';
        $expect .= '100011 100012 100013 100014 100015 100016 100017 100018 100019 100020 100021 ';
        $expect .= '100022 100023' . Client::MSG_SEPARATOR;
        $expect .= '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH ANSWERED');
        $expect = sprintf(
            '* SEARCH 100002 100019%s17 OK UID SEARCH completed%s',
            Client::MSG_SEPARATOR,
            Client::MSG_SEPARATOR
        );
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH BCC apple');
        $expect = '* SEARCH 100003' . Client::MSG_SEPARATOR . '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH BODY world');
        $expect = '* SEARCH 100006' . Client::MSG_SEPARATOR . '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH CC dev3');
        $expect = sprintf(
            '* SEARCH 100022 100023%s17 OK UID SEARCH completed%s',
            Client::MSG_SEPARATOR,
            Client::MSG_SEPARATOR
        );
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH DELETED');
        $expect = sprintf(
            '* SEARCH 100007 100020%s17 OK UID SEARCH completed%s',
            Client::MSG_SEPARATOR,
            Client::MSG_SEPARATOR
        );
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH DRAFT');
        $expect = sprintf(
            '* SEARCH 100008 100021%s17 OK UID SEARCH completed%s',
            Client::MSG_SEPARATOR,
            Client::MSG_SEPARATOR
        );
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH FLAGGED');
        $expect = sprintf(
            '* SEARCH 100009 100022%s17 OK UID SEARCH completed%s',
            Client::MSG_SEPARATOR,
            Client::MSG_SEPARATOR
        );
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH FROM test@');
        $expect = '* SEARCH 100010' . Client::MSG_SEPARATOR . '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH HEADER Date 1987');
        $expect = sprintf(
            '* SEARCH 100004 100005%s17 OK UID SEARCH completed%s',
            Client::MSG_SEPARATOR,
            Client::MSG_SEPARATOR
        );
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH HEADER TO fox21');
        $expect = '* SEARCH 100001 100002 100003 100004 100005 100006 100007 100008 100009 100010 100011 100012 ';
        $expect .= '100013 100014 100015 100016 100018 100019 100020 100021 100022 100023' . Client::MSG_SEPARATOR;
        $expect .= '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH LARGER 40');
        $expect = '* SEARCH 100011' . Client::MSG_SEPARATOR . '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH NEW');
        $expect = '* SEARCH 100012' . Client::MSG_SEPARATOR . '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH OLD');
        $expect = '* SEARCH 100001 100002 100003 100004 100005 100006 100007 100008 100009 100010 100011 100013';
        $expect .= ' 100014 100015 100016 100017 100018 100019 100020 100021 100022 100023' . Client::MSG_SEPARATOR;
        $expect .= '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH ON 1987-02-21');
        $expect = sprintf(
            '* SEARCH 100004 100005%s17 OK UID SEARCH completed%s',
            Client::MSG_SEPARATOR,
            Client::MSG_SEPARATOR
        );
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH OR 5 6');
        $expect = sprintf(
            '* SEARCH 100005 100006%s17 OK UID SEARCH completed%s',
            Client::MSG_SEPARATOR,
            Client::MSG_SEPARATOR
        );
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH OR OR 5 6 7');
        $expect = '* SEARCH 100005 100006 100007' . Client::MSG_SEPARATOR;
        $expect .= '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH RECENT');
        $expect = '* SEARCH 100012' . Client::MSG_SEPARATOR . '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH SEEN');
        $expect = '* SEARCH 100001 100003 100004 100005 100006 100010 100011 100023' . Client::MSG_SEPARATOR;
        $expect .= '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH SENTBEFORE 1990-01-01');
        $expect = '* SEARCH 100004 100005 100006' . Client::MSG_SEPARATOR;
        $expect .= '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH SENTON 1987-02-21');
        $expect = sprintf(
            '* SEARCH 100004 100005%s17 OK UID SEARCH completed%s',
            Client::MSG_SEPARATOR,
            Client::MSG_SEPARATOR
        );
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH SENTSINCE 1987-02-21');
        $expect = '* SEARCH 100001 100002 100003 100004 100005 100007 100008 100009 100010 100011 100012 100013';
        $expect .= ' 100014 100015 100016 100017 100018 100019 100020 100021 100022 100023' . Client::MSG_SEPARATOR;
        $expect .= '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH SMALLER 30');
        $expect = '* SEARCH 100001 100002 100003 100004 100005 100006 100007 100008 100009 100010 100012 100013';
        $expect .= ' 100014 100015 100016 100017 100018 100019 100020 100021 100022 100023' . Client::MSG_SEPARATOR;
        $expect .= '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH SUBJECT "t 13"');
        $expect = '* SEARCH 100013' . Client::MSG_SEPARATOR . '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH TEXT test');
        $expect = sprintf(
            '* SEARCH 100011 100015%s17 OK UID SEARCH completed%s',
            Client::MSG_SEPARATOR,
            Client::MSG_SEPARATOR
        );
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH TO steve');
        $expect = '* SEARCH 100017' . Client::MSG_SEPARATOR . '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH UID 100018');
        $expect = '* SEARCH 100018' . Client::MSG_SEPARATOR . '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH UNANSWERED');
        $expect = '* SEARCH 100001 100003 100004 100005 100006 100007 100008 100009 100010 100011 100012 100013';
        $expect .= ' 100014 100015 100016 100017 100018 100020 100021 100022 100023' . Client::MSG_SEPARATOR;
        $expect .= '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH UNDELETED');
        $expect = '* SEARCH 100001 100002 100003 100004 100005 100006 100008 100009 100010 100011 100012 100013';
        $expect .= ' 100014 100015 100016 100017 100018 100019 100021 100022 100023' . Client::MSG_SEPARATOR;
        $expect .= '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH UNDRAFT');
        $expect = '* SEARCH 100001 100002 100003 100004 100005 100006 100007 100009 100010 100011 100012 100013';
        $expect .= ' 100014 100015 100016 100017 100018 100019 100020 100022 100023' . Client::MSG_SEPARATOR;
        $expect .= '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH UNFLAGGED');
        $expect = '* SEARCH 100001 100002 100003 100004 100005 100006 100007 100008 100010 100011 100012 100013';
        $expect .= ' 100014 100015 100016 100017 100018 100019 100020 100021 100023' . Client::MSG_SEPARATOR;
        $expect .= '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        #$msg = $client->msgHandle('17 uid SEARCH UNKEYWORD');
        $expect = '* SEARCH 123' . Client::MSG_SEPARATOR . '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        #$this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH UNSEEN');
        $expect = '* SEARCH 100002 100007 100008 100009 100012 100013 100014 100015 100016 100017 100018';
        $expect .= ' 100019 100020 100021 100022' . Client::MSG_SEPARATOR;
        $expect .= '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH OR CC dev3 TO steve');
        $expect = '* SEARCH 100017 100022 100023';
        $expect .= '' . Client::MSG_SEPARATOR;
        $expect .= '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH OR CC dev3 NOT CC dev2');
        $expect = '* SEARCH 100001 100002 100003 100004 100005 100006 100007 100008 100009 100010 100011';
        $expect .= ' 100012 100013 100014 100015 100016 100017';
        $expect .= ' 100018 100019 100020 100021 100022 100023' . Client::MSG_SEPARATOR;
        $expect .= '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH CC dev3 AND TO dev2');
        $expect = '';
        $expect .= '* SEARCH 100022 100023' . Client::MSG_SEPARATOR;
        $expect .= '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH CC dev3 AND OR FROM dev1 TO dev2');
        $expect = '';
        $expect .= '* SEARCH 100022 100023' . Client::MSG_SEPARATOR;
        $expect .= '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH CC dev3 AND AND FROM dev1 NOT TO dev2');
        $expect = '';
        $expect .= '17 OK UID SEARCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH BEFORE 1985');
        $this->assertEquals('17 OK UID SEARCH completed' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('17 uid SEARCH BODY xyz');
        $this->assertEquals('17 OK UID SEARCH completed' . Client::MSG_SEPARATOR, $msg);

        #$msg = $client->msgHandle('17 uid SEARCH OR (UNDELETED FROM "thefox") ANSWERED AND FROM "21"');

        #$msg = $client->msgHandle('17 uid SEARCH UNDELETED HEADER From @fox21.at HEADER Date 2014');

        #$msg = $client->msgHandle('17 uid SEARCH NOT 5');
        #$msg = $client->msgHandle('17 uid SEARCH NOT UID 100005');
        #$this->assertEquals('x17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);

        #$msg = $client->msgHandle('17 uid SEARCH NOT UID 100021');

        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($path1);
    }

    public function testMsgHandleUidFetch1()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket('15 UID fetch');
        $this->assertEquals('15 NO uid failure' . Client::MSG_SEPARATOR, $msg);

        $client->setStatus('hasAuth', true);

        $msg = $client->handleRawPacket('15 UID fetch');
        $this->assertEquals('15 NO No mailbox selected.' . Client::MSG_SEPARATOR, $msg);

        $client->handleRawPacket('6 select INBOX');

        $msg = $client->handleRawPacket('15 UID fetch');
        $this->assertEquals('15 BAD Arguments invalid.' . Client::MSG_SEPARATOR, $msg);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $server->addMail($message, null, [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $server->addMail($message, null, [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $server->addMail($message, null, [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $server->addMail($message, null, [], true);

        $msg = $client->handleRawPacket('15 UID fetch 1:* (ALL)');
        $expect = '* 1 FETCH (UID 100001)' . Client::MSG_SEPARATOR;
        $expect .= '* 2 FETCH (UID 100002)' . Client::MSG_SEPARATOR;
        $expect .= '* 3 FETCH (UID 100003)' . Client::MSG_SEPARATOR;
        $expect .= '* 4 FETCH (UID 100004)' . Client::MSG_SEPARATOR;
        $expect .= '15 OK UID FETCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('15 UID fetch 1:* (FAST)');
        $expect = '* 1 FETCH (UID 100001)' . Client::MSG_SEPARATOR;
        $expect .= '* 2 FETCH (UID 100002)' . Client::MSG_SEPARATOR;
        $expect .= '* 3 FETCH (UID 100003)' . Client::MSG_SEPARATOR;
        $expect .= '* 4 FETCH (UID 100004)' . Client::MSG_SEPARATOR;
        $expect .= '15 OK UID FETCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $msg = $client->handleRawPacket('15 UID fetch 1:* (FULL)');
        $expect = '* 1 FETCH (UID 100001)' . Client::MSG_SEPARATOR;
        $expect .= '* 2 FETCH (UID 100002)' . Client::MSG_SEPARATOR;
        $expect .= '* 3 FETCH (UID 100003)' . Client::MSG_SEPARATOR;
        $expect .= '* 4 FETCH (UID 100004)' . Client::MSG_SEPARATOR;
        $expect .= '15 OK UID FETCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);
    }

    public function testMsgHandleUidFetch2()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $client->setStatus('hasAuth', true);
        $client->handleRawPacket('6 select INBOX');

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $server->addMail($message, null, [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $server->addMail($message, null, [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $server->addMail($message, null, null, false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $server->addMail($message, null, [], true);

        $msg = $client->handleRawPacket('15 UID fetch 1:* (FLAGS)');
        $expect = '';
        $expect .= '* 1 FETCH (UID 100001 FLAGS (' . Storage::FLAG_RECENT . '))' . Client::MSG_SEPARATOR;
        $expect .= '* 2 FETCH (UID 100002 FLAGS (' . Storage::FLAG_RECENT . '))' . Client::MSG_SEPARATOR;
        $expect .= '* 3 FETCH (UID 100003 FLAGS (' . Storage::FLAG_SEEN . '))' . Client::MSG_SEPARATOR;
        $expect .= '* 4 FETCH (UID 100004 FLAGS (' . Storage::FLAG_RECENT . '))' . Client::MSG_SEPARATOR;
        $expect .= '15 OK UID FETCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);
    }

    public function testMsgHandleUidFetch3()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $client->setStatus('hasAuth', true);
        $client->handleRawPacket('6 select INBOX');

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $server->addMail($message, null, [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $server->addMail($message, null, [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $server->addMail($message, null, [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $server->addMail($message, null, [], true);

        $msg = $client->handleRawPacket('15 UID fetch 100002:100004 (FLAGS)');
        $expect = '';
        $expect = '* 2 FETCH (UID 100002 FLAGS (' . Storage::FLAG_RECENT . '))' . Client::MSG_SEPARATOR;
        $expect .= '* 3 FETCH (UID 100003 FLAGS (' . Storage::FLAG_RECENT . '))' . Client::MSG_SEPARATOR;
        $expect .= '* 4 FETCH (UID 100004 FLAGS (' . Storage::FLAG_RECENT . '))' . Client::MSG_SEPARATOR;
        $expect .= '15 OK UID FETCH completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);
    }

    public function testMsgHandleUidFetch4()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $client->setStatus('hasAuth', true);
        $client->handleRawPacket('6 select INBOX');

        $message1 = new Message();
        $message1->addFrom('dev1@fox21.at');
        $message1->addTo('dev2@fox21.at');
        $message1->setSubject('my_subject 1');
        $message1->setBody('my_body');
        $message1Date = new DateTime($message1->getHeaders()->get('Date')->getFieldValue());
        $server->addMail($message1, null, [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $server->addMail($message, null, [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $server->addMail($message, null, [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $server->addMail($message, null, [], true);

        $rawMsg = '';
        $rawMsg .= '15 UID fetch 100001';
        $rawMsg .= ' (UID RFC822.SIZE FLAGS BODY.PEEK';
        $rawMsg .= '[HEADER.FIELDS (';
        $rawMsg .= 'From To Cc Bcc Subject Date Message-ID Priority X-Priority References';
        $rawMsg .= ' Newsgroups In-Reply-To Content-Type Reply-To';
        $rawMsg .= ')])';
        $msg = $client->handleRawPacket($rawMsg);

        $expect = '';
        $expect .= '* 1 FETCH (UID 100001 RFC822.SIZE 111 FLAGS (\Recent) BODY[HEADER] {104}' . Client::MSG_SEPARATOR;
        $expect .= 'From: dev1@fox21.at' . Client::MSG_SEPARATOR;
        $expect .= 'To: dev2@fox21.at' . Client::MSG_SEPARATOR;
        $expect .= 'Subject: my_subject 1' . Client::MSG_SEPARATOR;
        $expect .= 'Date: ' . $message1Date->format(DateTime::RFC1123) . Client::MSG_SEPARATOR;
        $expect .= '' . Client::MSG_SEPARATOR;
        $expect .= ')' . Client::MSG_SEPARATOR;
        $expect .= '15 OK UID FETCH completed' . Client::MSG_SEPARATOR;

        $this->assertEquals($expect, $msg);
    }

    public function testMsgHandleUidStore()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket('18 UID store');
        $this->assertEquals('18 NO uid failure' . Client::MSG_SEPARATOR, $msg);

        $msgRaw = sprintf('18 uid store 100001 +FLAGS (%s %s)', Storage::FLAG_DELETED, Storage::FLAG_SEEN);
        $msg = $client->handleRawPacket($msgRaw);
        $this->assertEquals('18 NO uid failure' . Client::MSG_SEPARATOR, $msg);

        $client->setStatus('hasAuth', true);
        $client->handleRawPacket('6 select INBOX');

        $msgRaw = sprintf('18 uid store 100001 +FLAGS (%s %s)', Storage::FLAG_DELETED, Storage::FLAG_SEEN);
        $msg = $client->handleRawPacket($msgRaw);
        $this->assertEquals('18 OK UID STORE completed' . Client::MSG_SEPARATOR, $msg);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $server->addMail($message, null, [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $server->addMail($message, null, [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $server->addMail($message, null, [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $server->addMail($message, null, [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 5');
        $message->setBody('my_body');
        $server->addMail($message, null, [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 6');
        $message->setBody('my_body');
        $server->addMail($message, null, [], false);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 7');
        $message->setBody('my_body');
        $server->addMail($message, null, null, false);

        $this->assertEquals(5, count($server->getMailIdsByFlags([Storage::FLAG_RECENT])));

        $msg = $client->handleRawPacket('18 uid store 100001 -FLAGS (' . Storage::FLAG_RECENT . ')');
        $expect = '* 1 FETCH (FLAGS ())' . Client::MSG_SEPARATOR . '18 OK UID STORE completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $this->assertEquals(4, count($server->getMailIdsByFlags([Storage::FLAG_RECENT])));

        $msg = $client->handleRawPacket('18 uid store 100001 +FLAGS (' . Storage::FLAG_SEEN . ')');
        $expect = '* 1 FETCH (FLAGS (' . Storage::FLAG_SEEN . '))' . Client::MSG_SEPARATOR;
        $expect .= '18 OK UID STORE completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $this->assertEquals(2, count($server->getMailIdsByFlags([Storage::FLAG_SEEN])));

        $msg = $client->handleRawPacket('18 uid store 100002 +FLAGS (' . Storage::FLAG_SEEN . ')');
        $expect = '* 2 FETCH (FLAGS (' . Storage::FLAG_SEEN . '))' . Client::MSG_SEPARATOR;
        $expect .= '18 OK UID STORE completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $this->assertEquals(3, count($server->getMailIdsByFlags([Storage::FLAG_SEEN])));

        $msgRaw = sprintf(
            '18 uid store 100003 +FLAGS (%s %s)',
            Storage::FLAG_SEEN,
            Storage::FLAG_ANSWERED
        );
        $msg = $client->handleRawPacket($msgRaw);
        $expect = sprintf(
            '* 3 FETCH (FLAGS (%s %s))%s18 OK UID STORE completed%s',
            Storage::FLAG_SEEN,
            Storage::FLAG_ANSWERED,
            Client::MSG_SEPARATOR,
            Client::MSG_SEPARATOR
        );
        $this->assertEquals($expect, $msg);

        $this->assertEquals(4, count($server->getMailIdsByFlags([Storage::FLAG_SEEN, Storage::FLAG_ANSWERED])));

        $msgRaw = sprintf(
            '18 uid store 100003 +FLAGS (%s %s %s)',
            Storage::FLAG_SEEN,
            Storage::FLAG_ANSWERED,
            Storage::FLAG_FLAGGED
        );
        $msg = $client->handleRawPacket($msgRaw);
        $expect = sprintf(
            '* 3 FETCH (FLAGS (%s %s %s))%s18 OK UID STORE completed%s',
            Storage::FLAG_SEEN,
            Storage::FLAG_ANSWERED,
            Storage::FLAG_FLAGGED,
            Client::MSG_SEPARATOR,
            Client::MSG_SEPARATOR
        );
        $this->assertEquals($expect, $msg);

        $this->assertEquals(1, count($server->getMailIdsByFlags([Storage::FLAG_FLAGGED])));

        $msgRaw = sprintf(
            '18 uid store 100003 +FLAGS (%s %s %s %s)',
            Storage::FLAG_SEEN,
            Storage::FLAG_ANSWERED,
            Storage::FLAG_FLAGGED,
            Storage::FLAG_DELETED
        );
        $msg = $client->handleRawPacket($msgRaw);
        $expect = sprintf(
            '* 3 FETCH (FLAGS (%s %s %s %s))%s18 OK UID STORE completed%s',
            Storage::FLAG_SEEN,
            Storage::FLAG_ANSWERED,
            Storage::FLAG_FLAGGED,
            Storage::FLAG_DELETED,
            Client::MSG_SEPARATOR,
            Client::MSG_SEPARATOR
        );
        $this->assertEquals($expect, $msg);

        $this->assertEquals(1, count($server->getMailIdsByFlags([Storage::FLAG_DELETED])));

        $raw = '18 uid store 100003 +FLAGS (';
        $raw .= Storage::FLAG_SEEN . ' ' . Storage::FLAG_ANSWERED . ' ' . Storage::FLAG_FLAGGED;
        $raw .= ' ' . Storage::FLAG_DELETED . ' ' . Storage::FLAG_DRAFT . ')';
        $msg = $client->handleRawPacket($raw);
        $expect = '* 3 FETCH (FLAGS (';
        $expect .= Storage::FLAG_SEEN . ' ' . Storage::FLAG_ANSWERED . ' ' . Storage::FLAG_FLAGGED . ' ';
        $expect .= Storage::FLAG_DELETED . ' ' . Storage::FLAG_DRAFT . '))' . Client::MSG_SEPARATOR;
        $expect .= '18 OK UID STORE completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $this->assertEquals(1, count($server->getMailIdsByFlags([Storage::FLAG_DRAFT])));

        $raw = '18 uid store 100003 +FLAGS (';
        $raw .= Storage::FLAG_SEEN . ' ' . Storage::FLAG_ANSWERED . ' ' . Storage::FLAG_FLAGGED;
        $raw .= ' ' . Storage::FLAG_DELETED . ' ' . Storage::FLAG_DRAFT . ')';
        $msg = $client->handleRawPacket($raw);
        $expect = '* 3 FETCH (FLAGS (';
        $expect .= Storage::FLAG_SEEN . ' ' . Storage::FLAG_ANSWERED . ' ' . Storage::FLAG_FLAGGED;
        $expect .= ' ' . Storage::FLAG_DELETED . ' ' . Storage::FLAG_DRAFT;
        $expect .= '))' . Client::MSG_SEPARATOR;
        $expect .= '18 OK UID STORE completed' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $this->assertEquals(1, count($server->getMailIdsByFlags([Storage::FLAG_DRAFT])));
        $this->assertEquals(2, count($server->getMailIdsByFlags([Storage::FLAG_RECENT])));

        $msgRaw = sprintf(
            '18 uid store 100003:100004 +FLAGS (%s %s %s %s %s)',
            Storage::FLAG_SEEN,
            Storage::FLAG_ANSWERED,
            Storage::FLAG_FLAGGED,
            Storage::FLAG_DELETED,
            Storage::FLAG_DRAFT
        );
        $msg = $client->handleRawPacket($msgRaw);
        $expect = sprintf(
            '* 3 FETCH (FLAGS (%s %s %s %s %s))%s* 4 FETCH (FLAGS (%s %s %s %s %s))%s18 OK UID STORE completed%s',
            Storage::FLAG_SEEN,
            Storage::FLAG_ANSWERED,
            Storage::FLAG_FLAGGED,
            Storage::FLAG_DELETED,
            Storage::FLAG_DRAFT,
            Client::MSG_SEPARATOR,
            Storage::FLAG_SEEN,
            Storage::FLAG_ANSWERED,
            Storage::FLAG_FLAGGED,
            Storage::FLAG_DELETED,
            Storage::FLAG_DRAFT,
            Client::MSG_SEPARATOR,
            Client::MSG_SEPARATOR
        );
        $this->assertEquals($expect, $msg);

        $this->assertEquals(2, count($server->getMailIdsByFlags([Storage::FLAG_DRAFT])));

        $msg = $client->handleRawPacket('18 uid store 100005 +FLAGS.SILENT (' . Storage::FLAG_FLAGGED . ')');
        $this->assertEquals('18 OK UID STORE completed' . Client::MSG_SEPARATOR, $msg);

        $this->assertEquals(3, count($server->getMailIdsByFlags([Storage::FLAG_FLAGGED])));

        $msg = $client->handleRawPacket('18 uid store 100005 -FLAGS.SILENT (' . Storage::FLAG_FLAGGED . ')');
        $this->assertEquals('18 OK UID STORE completed' . Client::MSG_SEPARATOR, $msg);

        $this->assertEquals(2, count($server->getMailIdsByFlags([Storage::FLAG_FLAGGED])));

        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($path1);
    }

    public function testMsgHandleUidFail()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $client->setStatus('hasAuth', true);
        $client->handleRawPacket('6 select INBOX');

        $msg = $client->handleRawPacket('18 uid FAIL1 FAIL2');
        $this->assertEquals('18 BAD Arguments invalid.' . Client::MSG_SEPARATOR, $msg);

        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($path1);
    }

    public function testMsgHandleCopy()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket('16 copy');
        $this->assertEquals('16 NO copy failure' . Client::MSG_SEPARATOR, $msg);

        $client->setStatus('hasAuth', true);

        $msg = $client->handleRawPacket('16 copy');
        $this->assertEquals('16 BAD Arguments invalid.' . Client::MSG_SEPARATOR, $msg);

        $server->addFolder('test_dir1');
        $server->addFolder('test_dir2');

        $msg = $client->handleRawPacket('16 copy 1 test_dir2');
        $this->assertEquals('16 NO No mailbox selected.' . Client::MSG_SEPARATOR, $msg);

        $client->handleRawPacket('6 select test_dir1');

        $msg = $client->handleRawPacket('16 copy');
        $this->assertEquals('16 BAD Arguments invalid.' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('16 copy 1');
        $this->assertEquals('16 BAD Arguments invalid.' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('16 copy 1 test_dir3');
        $this->assertEquals('16 BAD No messages in selected mailbox.' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('16 UID copy 1 test_dir2');
        $this->assertEquals('16 BAD No messages in selected mailbox.' . Client::MSG_SEPARATOR, $msg);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir1', [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir1', [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir1', [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir1', [], true);

        $finder = new Finder();
        $files = $finder->in($path1 . '/test_dir1')->files();
        $this->assertEquals(4, count($files));

        $msg = $client->handleRawPacket('15 copy 2 test_dir2');
        $this->assertEquals('15 OK COPY completed' . Client::MSG_SEPARATOR, $msg);
        $finder = new Finder();
        $files = $finder->in($path1 . '/test_dir2')->files();
        $this->assertEquals(1, count($files));

        $msg = $client->handleRawPacket('15 copy 3:4 test_dir2');
        $this->assertEquals('15 OK COPY completed' . Client::MSG_SEPARATOR, $msg);
        $finder = new Finder();
        $files = $finder->in($path1 . '/test_dir2')->files();
        $this->assertEquals(3, count($files));

        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($path1);
    }

    public function testMsgHandleUidCopy()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $client = new Client();
        $client->setServer($server);
        $client->setId(1);

        $msg = $client->handleRawPacket('15 UID copy');
        $this->assertEquals('15 NO uid failure' . Client::MSG_SEPARATOR, $msg);

        $client->setStatus('hasAuth', true);

        $msg = $client->handleRawPacket('15 UID copy');
        $this->assertEquals('15 NO No mailbox selected.' . Client::MSG_SEPARATOR, $msg);

        $server->addFolder('test_dir1');
        $server->addFolder('test_dir2');
        $client->handleRawPacket('6 select test_dir1');

        $msg = $client->handleRawPacket('15 UID copy');
        $this->assertEquals('15 BAD Arguments invalid.' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('15 UID copy 100001');
        $this->assertEquals('15 BAD Arguments invalid.' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('15 UID copy 100001 test_dir3');
        $this->assertEquals('15 BAD No messages in selected mailbox.' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('15 UID copy 100001 test_dir2');
        $this->assertEquals('15 BAD No messages in selected mailbox.' . Client::MSG_SEPARATOR, $msg);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir1', [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir1', [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir1', [], true);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $server->addMail($message, 'test_dir1', [], true);

        $finder = new Finder();
        $files = $finder->in($path1 . '/test_dir1')->files();
        $this->assertEquals(4, count($files));

        $msg = $client->handleRawPacket('15 UID copy 100002 test_dir2');
        $this->assertEquals('15 OK COPY completed' . Client::MSG_SEPARATOR, $msg);
        $finder = new Finder();
        $files = $finder->in($path1 . '/test_dir2')->files();
        $this->assertEquals(1, count($files));

        $msg = $client->handleRawPacket('15 UID copy 100003:100004 test_dir2');
        $this->assertEquals('15 OK COPY completed' . Client::MSG_SEPARATOR, $msg);
        $finder = new Finder();
        $files = $finder->in($path1 . '/test_dir2')->files();
        $this->assertEquals(3, count($files));

        $msg = $client->handleRawPacket('15 UID copy 1 test_dir2');
        $this->assertEquals('15 OK COPY completed' . Client::MSG_SEPARATOR, $msg);

        $msg = $client->handleRawPacket('15 UID copy 100001 test_dir3');
        $expect = '15 NO [TRYCREATE] Can not get folder: no subfolder named test_dir3' . Client::MSG_SEPARATOR;
        $this->assertEquals($expect, $msg);

        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($path1);
    }

    public function testSendOk()
    {
        $client = new Client();
        $client->setId(1);

        $this->assertEquals('* OK text1' . Client::MSG_SEPARATOR, $client->sendOk('text1'));
        $this->assertEquals('tag1 OK text1' . Client::MSG_SEPARATOR, $client->sendOk('text1', 'tag1'));
        $this->assertEquals('tag1 OK [code1] text1' . Client::MSG_SEPARATOR, $client->sendOk('text1', 'tag1', 'code1'));
        $this->assertEquals('* OK [code1] text1' . Client::MSG_SEPARATOR, $client->sendOk('text1', null, 'code1'));
    }

    public function testSendNo()
    {
        $client = new Client();
        $client->setId(1);

        $this->assertEquals('* NO text1' . Client::MSG_SEPARATOR, $client->sendNo('text1'));
        $this->assertEquals('tag1 NO text1' . Client::MSG_SEPARATOR, $client->sendNo('text1', 'tag1'));
        $this->assertEquals('tag1 NO [code1] text1' . Client::MSG_SEPARATOR, $client->sendNo('text1', 'tag1', 'code1'));
        $this->assertEquals('* NO [code1] text1' . Client::MSG_SEPARATOR, $client->sendNo('text1', null, 'code1'));
    }

    public function testSendBad()
    {
        $client = new Client();
        $client->setId(1);

        $this->assertEquals('* BAD text1' . Client::MSG_SEPARATOR, $client->sendBad('text1'));
        $this->assertEquals('tag1 BAD text1' . Client::MSG_SEPARATOR, $client->sendBad('text1', 'tag1'));
        
        $expected='tag1 BAD [code1] text1' . Client::MSG_SEPARATOR;
        $this->assertEquals($expected, $client->sendBad('text1', 'tag1', 'code1'));
        
        $this->assertEquals('* BAD [code1] text1' . Client::MSG_SEPARATOR, $client->sendBad('text1', null, 'code1'));
    }

    public function testSendPreauth()
    {
        $client = new Client();
        $client->setId(1);

        $this->assertEquals('* PREAUTH text1' . Client::MSG_SEPARATOR, $client->sendPreauth('text1'));
        $this->assertEquals('* PREAUTH [code1] text1' . Client::MSG_SEPARATOR, $client->sendPreauth('text1', 'code1'));
    }

    public function testSendBye()
    {
        $client = new Client();
        $client->setId(1);

        $this->assertEquals('* BYE text1' . Client::MSG_SEPARATOR, $client->sendBye('text1'));
        $this->assertEquals('* BYE [code1] text1' . Client::MSG_SEPARATOR, $client->sendBye('text1', 'code1'));
    }

    public function testSelect()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $server->addFolder('test_dir1');
        $server->addFolder('test_dir2');

        $client1 = new Client();
        $client1->setServer($server);
        $client1->setId(1);
        $client2 = new Client();
        $client2->setServer($server);
        $client2->setId(2);

        $client1->select('test_dir1');
        $this->assertEquals('test_dir1', $client1->getSelectedFolder());
        $this->assertEquals('', $client2->getSelectedFolder());

        $client1->select('test_dir2');
        $this->assertEquals('test_dir2', $client1->getSelectedFolder());
        $this->assertEquals('', $client2->getSelectedFolder());

        $client2->select('test_dir1');
        $this->assertEquals('test_dir2', $client1->getSelectedFolder());
        $this->assertEquals('test_dir1', $client2->getSelectedFolder());
    }
}
