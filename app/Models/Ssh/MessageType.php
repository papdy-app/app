<?php

declare(strict_types=1);

namespace App\Models\Ssh;

/**
 * SSH binary packet message type numbers (RFC 4253 / 4252 / 4254).
 *
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
final class MessageType
{
    public const int DISCONNECT = 1;
    public const int IGNORE = 2;
    public const int UNIMPLEMENTED = 3;
    public const int DEBUG = 4;
    public const int SERVICE_REQUEST = 5;
    public const int SERVICE_ACCEPT = 6;
    public const int KEXINIT = 20;
    public const int NEWKEYS = 21;
    public const int KEX_ECDH_INIT = 30;
    public const int KEX_ECDH_REPLY = 31;
    public const int USERAUTH_REQUEST = 50;
    public const int USERAUTH_FAILURE = 51;
    public const int USERAUTH_SUCCESS = 52;
    public const int USERAUTH_BANNER = 53;
    public const int GLOBAL_REQUEST = 80;
    public const int REQUEST_SUCCESS = 81;
    public const int REQUEST_FAILURE = 82;
    public const int CHANNEL_OPEN = 90;
    public const int CHANNEL_OPEN_CONFIRMATION = 91;
    public const int CHANNEL_OPEN_FAILURE = 92;
    public const int CHANNEL_WINDOW_ADJUST = 93;
    public const int CHANNEL_DATA = 94;
    public const int CHANNEL_EXTENDED_DATA = 95;
    public const int CHANNEL_EOF = 96;
    public const int CHANNEL_CLOSE = 97;
    public const int CHANNEL_REQUEST = 98;
    public const int CHANNEL_SUCCESS = 99;
    public const int CHANNEL_FAILURE = 100;

    private function __construct() {}
}
