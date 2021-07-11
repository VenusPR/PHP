<?php

/**
 * PHPMailer - PHP email transport unit tests.
 * PHP version 5.5.
 *
 * @author    Marcus Bointon <phpmailer@synchromedia.co.uk>
 * @author    Andy Prevost
 * @copyright 2012 - 2020 Marcus Bointon
 * @copyright 2004 - 2009 Andy Prevost
 * @license   http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */

namespace PHPMailer\Test\PHPMailer;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\Test\PreSendTestCase;

/**
 * Test reply-to address setting, getting and clearing functionality.
 */
final class ReplyToGetSetClearTest extends PreSendTestCase
{

    /**
     * Test adding a non-IDN reply-to address.
     *
     * @covers \PHPMailer\PHPMailer\PHPMailer::addReplyTo
     * @covers \PHPMailer\PHPMailer\PHPMailer::addOrEnqueueAnAddress
     * @covers \PHPMailer\PHPMailer\PHPMailer::addAnAddress
     * @covers \PHPMailer\PHPMailer\PHPMailer::getReplyToAddresses
     *
     * @dataProvider dataAddReplyToValidAddressNonIdn
     *
     * @param string $address  The email address to set.
     * @param string $name     Optional. The name to set.
     * @param string $expected Optional. The email address and name as they are expected to be set.
     *                         Only needs to be passed if different than the original inputs.
     */
    public function testAddReplyToValidAddressNonIdn($address, $name = null, $expected = null)
    {
        if (isset($name)) {
            $result = $this->Mail->addReplyTo($address, $name);
        } else {
            $result = $this->Mail->addReplyTo($address);
            $name   = '';
        }

        if (isset($expected) === false) {
            $expected = [
                'key'     => $address,
                'address' => $address,
                'name'    => $name,
            ];
        }

        // Test the setting is successful.
        self::assertTrue($result, 'Replyto Addressing failed');

        // Verify that the address was correctly added to the array.
        $retrieved = $this->Mail->getReplyToAddresses();
        self::assertIsArray($retrieved, 'ReplyTo property is not an array');
        self::assertCount(1, $retrieved, 'ReplyTo property does not contain exactly one address');

        $key = $expected['key'];
        self::assertArrayHasKey(
            $key,
            $retrieved,
            'ReplyTo property does not contain an entry with this address as the key'
        );
        self::assertCount(
            2,
            $retrieved[$key],
            'ReplyTo array for this address does not contain exactly two array items'
        );
        self::assertSame(
            $expected['address'],
            $retrieved[$key][0],
            'ReplyTo array for this address does not contain added address'
        );
        self::assertSame(
            $expected['name'],
            $retrieved[$key][1],
            'ReplyTo array for this address does not contain added name'
        );
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public function dataAddReplyToValidAddressNonIdn()
    {
        return [
            'Valid address' => [
                'address' => 'a@example.com',
            ],
            'Valid address with surrounding whitespace and mixed case' => [
                'address' => " \tMiXeD@Example.Com  \r\n",
                'name'    => null,
                'expected' => [
                    'key'     => 'mixed@example.com',
                    'address' => 'MiXeD@Example.Com',
                    'name'    => '',
                ],
            ],
            'Valid address with name' => [
                'address' => 'a@example.com',
                'name'    => 'ReplyTo name',
            ],
            'Valid address with name; name with whitespace and line breaks' => [
                'address'  => 'a@example.com',
                'name'     => "\t\t  ReplyTo\r\nname  ",
                'expected' => [
                    'key'     => 'a@example.com',
                    'address' => 'a@example.com',
                    'name'    => 'ReplyToname',
                ],
            ],
        ];
    }

    /**
     * Test adding an invalid non-IDN reply-to address.
     *
     * @covers \PHPMailer\PHPMailer\PHPMailer::addOrEnqueueAnAddress
     * @covers \PHPMailer\PHPMailer\PHPMailer::addAnAddress
     *
     * @dataProvider dataAddReplyToInvalidAddressNonIdn
     *
     * @param string $address The email address to set.
     */
    public function testAddReplyToInvalidAddressNonIdn($address)
    {
        // Test the setting fails.
        $result = $this->Mail->addReplyTo($address);
        self::assertFalse($result, 'Invalid Replyto address accepted');

        // Verify that the address was not added to the array.
        $retrieved = $this->Mail->getReplyToAddresses();
        self::assertIsArray($retrieved, 'ReplyTo property is not an array');
        self::assertCount(0, $retrieved, 'ReplyTo property is not empty');
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public function dataAddReplyToInvalidAddressNonIdn()
    {
        return [
            'Invalid domain' => ['a@example..com'],
            'Missing @ sign' => ['example.com'],
        ];
    }

    /**
     * Test low priority.
     */
    public function testLowPriority()
    {
        $this->Mail->Body = 'Here is the main body.  There should be ' .
            'a reply to address in this message.';
        $this->Mail->Subject .= ': Low Priority';
        $this->Mail->addReplyTo('nobody@nobody.com', 'Nobody (Unit Test)');

        $this->buildBody();
        self::assertTrue($this->Mail->preSend(), $this->Mail->ErrorInfo);
    }

    /**
     * Tests CharSet and Unicode -> ASCII conversions for addresses with IDN.
     *
     * @requires extension mbstring
     * @requires function idn_to_ascii
     */
    public function testConvertEncoding()
    {
        $this->Mail->clearReplyTos();

        //This file is UTF-8 encoded. Create a domain encoded in "iso-8859-1".
        $letter = html_entity_decode('&ccedil;', ENT_COMPAT, PHPMailer::CHARSET_ISO88591);
        $domain = '@' . 'fran' . $letter . 'ois.ch';
        $this->Mail->addReplyTo('test+replyto' . $domain);

        //Queued addresses are not returned by get*Addresses() before send() call.
        self::assertEmpty($this->Mail->getReplyToAddresses(), 'Bad "reply-to" recipients');

        $this->buildBody();
        self::assertTrue($this->Mail->preSend(), $this->Mail->ErrorInfo);

        //Addresses with IDN are returned by get*Addresses() after send() call.
        $domain = $this->Mail->punyencodeAddress($domain);
        self::assertSame(
            ['test+replyto' . $domain => ['test+replyto' . $domain, '']],
            $this->Mail->getReplyToAddresses(),
            'Bad "reply-to" addresses'
        );
    }

    /**
     * Tests removal of duplicate recipients and reply-tos.
     *
     * @requires extension mbstring
     * @requires function idn_to_ascii
     */
    public function testDuplicateIDNRemoved()
    {
        $this->Mail->clearReplyTos();

        $this->Mail->CharSet = PHPMailer::CHARSET_UTF8;

        self::assertTrue($this->Mail->addReplyTo('test+replyto@françois.ch'));
        self::assertFalse($this->Mail->addReplyTo('test+replyto@françois.ch'));
        self::assertTrue($this->Mail->addReplyTo('test+replyto@FRANÇOIS.CH'));
        self::assertFalse($this->Mail->addReplyTo('test+replyto@FRANÇOIS.CH'));
        self::assertTrue($this->Mail->addReplyTo('test+replyto@xn--franois-xxa.ch'));
        self::assertFalse($this->Mail->addReplyTo('test+replyto@xn--franois-xxa.ch'));
        self::assertFalse($this->Mail->addReplyTo('test+replyto@XN--FRANOIS-XXA.CH'));

        $this->buildBody();
        self::assertTrue($this->Mail->preSend(), $this->Mail->ErrorInfo);

        //There should be only one "Reply-To" address.
        self::assertCount(
            1,
            $this->Mail->getReplyToAddresses(),
            'Bad count of "reply-to" addresses'
        );
    }

    public function testGivenIdnAddress_addReplyTo_returns_true()
    {
        if (file_exists(\PHPMAILER_INCLUDE_DIR . '/test/fakefunctions.php') === false) {
            $this->markTestSkipped('/test/fakefunctions.php file not found');
        }

        include \PHPMAILER_INCLUDE_DIR . '/test/fakefunctions.php';
        $this->assertTrue($this->Mail->addReplyTo('test@françois.ch'));
    }
}
