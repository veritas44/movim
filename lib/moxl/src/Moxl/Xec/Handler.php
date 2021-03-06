<?php
/*
 * @file Handler.php
 *
 * @brief Handle incoming XMPP request and dispatch them to the correct
 * XECElement
 *
 * Copyright 2012 edhelas <edhelas@edhelas-laptop>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 *
 *
 */

namespace Moxl\Xec;

use Moxl\Utils;
use Movim\Session;

class Handler
{
    /**
     * Constructor of class Handler.
     *
     * @return void
     */
    static public function handle($child)
    {
        $_instances = 'empty';

        $id = '';
        $element = '';

        // Id verification in the returned stanza
        if (in_array($child->getName(), ['iq', 'presence', 'message'])) {
            $id = (string)$child->attributes()->id;
        }

        $sess = Session::start();

        if (($id != '' && $sess->get($id) == false)
        || $id == '') {
            Utils::log("Handler : Memory instance not found for {$id}");
            Utils::log('Handler : Not an XMPP ACK');

            Handler::handleNode($child);

            foreach($child->children() as $s1) {
                Handler::handleNode($s1, $child);
                foreach($s1->children() as $s2) {
                    Handler::handleNode($s2, $child);
                }
            }
        } elseif ($id != ''
        && $sess->get($id) != false) {
            // We search an existent instance
            Utils::log("Handler : Memory instance found for {$id}");
            $instance = $sess->get($id);

            $action = unserialize($instance->object);

            $error = false;

            // Handle specific query error
            if ($child->query->error) {
                $error = $child->query->error;
            } elseif ($child->error) {
                $error = $child->error;
            }

            // XMPP returned an error
            if ($error) {
                $errors = $error->children();

                $errorid = Handler::formatError($errors->getName());

                $message = false;

                if ($error->text) {
                    $message = (string)$error->text;
                }

                Utils::log('Handler : '.get_class($action).' '.$id.' - '.$errorid);

                /* If the action has defined a special handler
                 * for this error
                 */
                if (method_exists($action, $errorid)) {
                    $action->method($errorid);
                    $action->$errorid($errorid, $message);
                }
                // We also call a global error handler
                if (method_exists($action, 'error')) {
                    Utils::log('Handler : Global error - '.$id.' - '.$errorid);
                    $action->method('error');
                    $action->error($errorid, $message);
                }
            } elseif (method_exists($action, 'handle')) {
                // We launch the object handle
                $action->method('handle');
                $action->handle($child);
            }
            // We clean the object from the cache
            $sess->remove($id);
        }
    }

    static public function handleNode($s, $sparent = false)
    {
        $name = $s->getName();
        $ns = '';

        foreach($s->attributes() as $key => $value) {
            if (($key == 'xmlns' && $ns == '')
            || 'xmlns:' === substr($key, 0, 6)) {
                $ns = $value;
            }
        }

        if ($s->items && $s->items->attributes()->node) {
            $node = (string)$s->items->attributes()->node;
            $hash = md5($name.$ns.$node);
            Utils::log('Handler : Searching a payload for "'.$name . ':' . $ns . ' [' . $node . ']", "'.$hash.'"');
            Handler::searchPayload($hash, $s, $sparent);
        }

        $hash = md5($name.$ns);
        Utils::log('Handler : Searching a payload for "'.$name . ':' . $ns . '", "'.$hash.'"');
        Handler::searchPayload($hash, $s, $sparent);
    }

    static public function searchPayload($hash, $s, $sparent = false)
    {
        $hashToClass = [
            '9a534a8b4d6324e23f4187123e406729' => 'Message',
            '78e731027d8fd50ed642340b7c9a63b3' => 'Message',// TLS

            'f9e18585fd0e0873c52e880c800f267a' => 'ReceiptAck',
            '004a75eb0a92fca2b868732b56863e66' => 'ReceiptRequest',
            '3ca6c24643a9389b91323ddd1aaa84d0' => 'Displayed',

            '0977b7387b95f69007332a3e9b386f93' => 'MAMResult', // mam:0
            'fd60f5fdd5d2a06d1c4dd723032fb41a' => 'MAMResult', // mam:1
            '0e49eb65ba266051d2a2287660e22ab1' => 'MAMResult', // mam:2

            '1040105fc01bfac8a5ab81324875e382' => 'Presence',
            '362b908ec9432a506f86bed0bae7bbb6' => 'Presence',// TLS
            'a0e8e987b067b6b0470606f4f90d5362' => 'Roster',

            'fa9d41e26f664d9056618a4afe213861' => 'Post',

            '53b95afd89dcb7199dfcca39a90592eb' => 'Confirm', // XEP-0070

            '9952d726429340d482ecac82c1496191' => 'BOB',

            '4c9681f0e9aca8a5b65f86b8b80d490f' => 'DiscoInfo',
            '2bf34d156903518b18e58b4786c25d3b' => 'DiscoItems',

            '37ff18f136d5826c4426af5a23729e48' => 'Mood',
            '6b38ed328fb77617c6e4a5ac9dda0ad2' => 'Tune',
            '0981a46bbfa88b3500c4bccda18ccb89' => 'Location',
            '9c8ed44d4528a66484b0fbd44b0a9070' => 'Nickname',

            'd8ea912a151202700bb399c9e04d205f' => 'Caps',

            '40ed26a65a25ab8bf809dd998d541d95' => 'PingPong',

            'cb52f989717d25441018703ea1bc9819' => 'Attention',

            '54c22c37d17c78ee657ea3d40547a970' => 'Version',

            '1cb493832467273efa384bbffa6dc35a' => 'Avatar',
            '0f59aa7fb0492a008df1b807e91dda3b' => 'AvatarMetadata',
            '36fe2745bdc72b1682be2c008d547e3d' => 'Vcard4',

            'd84d4b89d43e88a244197ccf499de8d8' => 'Jingle',

            '09ef1b34cf40fdd954f10d6e5075ee5c' => 'Carbons',
            '201fa54dd93e3403611830213f5f9fbc' => 'Carbons',//?

            'b95746de5ddc3fa5fbf28906c017d9d8' => 'STARTTLS',

            'f728271d924a04b0355379b28c3183a1' => 'SASL',
            '5e291b72f7160dabd1aa28f90cbde769' => 'SASLChallenge',
            'abae1d63bb4295636badcce1bee02290' => 'SASLChallenge', // TLS
            'a5af6a9efd75060b5aca9b473f1ef756' => 'SASLSuccess',
            '53936dd4e1d64e1eeec6dfc95c431964' => 'SASLSuccess', // TLS
            'de175adc9063997df5b79817576ff659' => 'SASLFailure',
            '0bc0f510b2b6ac432e8605267ebdc812' => 'SessionBind',#
            '128477f50347d98ee1213d71f27e8886' => 'SessionBind',
        ];
        if (isset($hashToClass[$hash])) {
            $classname = '\\Moxl\\Xec\\Payload\\'.$hashToClass[$hash];

            $payload_class = new $classname();
            $payload_class->prepare($s, $sparent);
            $payload_class->handle($s, $sparent);
        } else {
            Utils::log('Handler : This event is not listed');
            return true;
        }
    }

    /**
     * A simple function to format a error-string-text to a
     * camelTypeText
     */
    static public function formatError($string)
    {
        $words = explode('-', $string);
        $f = 'error';

        foreach($words as $word) {
            $f .= ucfirst($word);
        }

        return $f;
    }
}
