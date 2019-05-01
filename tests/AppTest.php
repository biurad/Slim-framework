<?php
/*
        This code is under MIT License

        +--------------------------------+
        |   DO NOT MODIFY THIS HEADERS   |
        +--------------------------------+-----------------+
        |   Created by BiuStudio                           |
        |   Email: support@biuhub.net                      |
        |   Link: https://www.biurad.tk                    |
        |   Source: https://github.com/biustudios/biurad   |
        |   Real Name: Divine Niiquaye - Ghana             |
        |   Copyright Copyright (c) 2018-2019 BiuStudio    |
        |   License: https://biurad.tk/LICENSE.md          |
        +--------------------------------------------------+

        +--------------------------------------------------------------------------------+
        |   Version: 0.0.1.1, Relased at 18/02/2019 13:13 (GMT + 1.00)                       |
        +--------------------------------------------------------------------------------+

        +----------------+
        |   Tested on    |
        +----------------+-----+
        |  APACHE => 2.0.55    |
        |     PHP => 5.4       |
        +----------------------+

        +---------------------+
        |  How to report bug  |
        +---------------------+-----------------------------------------------------------------+
        |   You can e-mail me using the email addres written above. That email is also my msn   |
        |   contact, so you can use it for contact me on MSN.                                   |
        +---------------------------------------------------------------------------------------+

        +-----------+
        |  Notes    |
        +-----------+------------------------------------------------------------------------------------------------+
        |   - BiuRad's simple-as-possible architecture was inspired by several conference talks, slides              |
        |     and articles about php frameworks that - surprisingly and intentionally -                              |
        |     go back to the basics of programming, using procedural programming, static classes,                    |
        |     extremely simple constructs, not-totally-DRY code etc. while keeping the code extremely readable.      |
        |   - Features of Biuraad Php Framework
        |     +--> Proper security features, like CSRF blocking (via form tokens), encryption of cookie contents etc.|
        |     +--> Built with the official PHP password hashing functions, fitting the most modern password          |
                   hashing/salting web standards.                                                                    |
        |     +--> Uses [Post-Redirect-Get pattern](https://en.wikipedia.org/wiki/Post/Redirect/Get)                 |
        |     <--+ Uses URL rewriting ("beautiful URLs").                                                            |
        |   - Masses of comments                                                                                     |                                                                              |
        |     +--> Uses Libraries including Composer to load external dependencies.                                  |
        |     <--+ Proper security features, like CSRF blocking (via form tokens), encryption of cookie contents etc.|
        |   - Fits PSR-0/1/2/4 coding guideline.                                                                     |
        +------------------------------------------------------------------------------------------------------------+

        +------------------+
        |  Special Thanks  |
        +------------------+-----------------------------------------------------------------------------------------+
        |  I always thank the HTML FORUM COMMUNITY (http://www.html.it) for the advice about the regular expressions |
        |  A special thanks at github.com(http://www.github.com), because they provide me the list of php libraries, |
        |  snippets, and any more...                                                                                 |
        |  I thanks Php.net and Sololearn.com for its guildline in PHP Programming                                   |
        |  Finally, i thank Wikipedia for the countries's icons 20px                                                 |
        +------------------------------------------------------------------------------------------------------------+
*/
/**
 * PHP Unit Testing.
 */
use PHPUnit\Framework\TestCase;
use Radion\CacheManager as Cache;
use Radion\Container;
use Radion\DatabaseManager as Database;
use Radion\ServiceManager as Service;
use Radion\SharerManager as Sharer;
use Radion\TimeTrackr;

/**
 * Class AppTest.
 */
class AppTest extends TestCase
{
    /**
     * @return string
     */
    public function testDependencies()
    {
        try {
            echo "\n";
            echo 'Creating a new container...';
            $app = new Container();
            self::displayOK();

            echo 'Load TimeTrackr config...';
            TimeTrackr::applyConfig();
            self::displayOK();

            echo 'Connecting to the database...';
            Database::loadConfig();
            $app->link('database', Database::getInstance());
            self::displayOK();

            echo 'Instantiate cachemanager... ';
            Cache::loadConfig();
            $app->link('cachemanager', Cache::getInstance());
            self::displayOK();

            echo 'Instantiate service manager and load all services...';
            $app->link('service', Service::loadServices());
            self::displayOK();

            echo 'Sharing the app...';
            Sharer::share('app', $app);
            self::displayOK();
        } catch (\Exception $exception) {
            self::displayFailed();
            static::assertTrue(false);

            return 'first';
        }
        static::assertTrue(true);

        return 'first';
    }

    public function displayOK()
    {
        echo "     OK \n";
    }

    public function displayFailed()
    {
        echo "     FAILED \n";
    }
}
