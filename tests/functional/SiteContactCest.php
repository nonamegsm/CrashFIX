<?php

declare(strict_types=1);

/**
 * Public site pages: contact form and login navigation.
 */
class SiteContactCest
{
    public function contactPageLoads(FunctionalTester $I): void
    {
        $I->amOnPage('/index.php?r=site%2Fcontact');
        $I->see('Contact', 'h1');
        $I->seeElement('#contact-form');
        $I->see('Verification Code');
    }

    public function contactFormSubmitsWithTestCaptcha(FunctionalTester $I): void
    {
        $I->amOnPage('/index.php?r=site%2Fcontact');
        $I->submitForm('#contact-form', [
            'ContactForm[name]' => 'Tester',
            'ContactForm[email]' => 'tester@example.com',
            'ContactForm[subject]' => 'Functional test',
            'ContactForm[body]' => 'Message from Codeception.',
            'ContactForm[verifyCode]' => 'testme',
        ], 'contact-button');
        $I->see('Thank you for contacting us');
    }

    public function loginPageShowsContactAndRecoverLinks(FunctionalTester $I): void
    {
        $I->amOnPage('/index.php?r=site%2Flogin');
        $I->see('Sign in');
        $I->see('Recover it');
        $I->see('Contact');
        $I->seeLink('Contact');
        $I->seeLink('Recover it');
    }
}
