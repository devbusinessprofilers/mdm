<?php

declare(strict_types=1);

namespace App\Tests\Pim\Validation;

use App\Pim\Validation\LienVideo;
use App\Pim\Validation\LienVideoValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/** @extends ConstraintValidatorTestCase<LienVideoValidator> */
final class LienVideoValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): LienVideoValidator
    {
        return new LienVideoValidator();
    }

    public function testNullEtVideNeDeclenchentRien(): void
    {
        $this->validator->validate(null, new LienVideo());
        $this->validator->validate('', new LienVideo());
        $this->assertNoViolation();
    }

    #[DataProvider('hebergeursAutorises')]
    public function testAccepteLesHebergeursAutorises(string $url): void
    {
        $this->validator->validate($url, new LienVideo());
        $this->assertNoViolation();
    }

    /** @return iterable<string, array{string}> */
    public static function hebergeursAutorises(): iterable
    {
        yield 'YouTube watch' => ['https://www.youtube.com/watch?v=xzaxUF8Wsvc'];
        yield 'YouTube court' => ['https://youtu.be/xzaxUF8Wsvc'];
        yield 'YouTube sans www' => ['https://youtube.com/embed/xzaxUF8Wsvc'];
        yield 'Vimeo' => ['https://vimeo.com/123456'];
        yield 'Vimeo player' => ['https://player.vimeo.com/video/123456'];
        yield 'Dailymotion' => ['https://www.dailymotion.com/video/x8abc'];
        yield 'Dailymotion court' => ['https://dai.ly/x8abc'];
        yield 'TikTok' => ['https://www.tiktok.com/@compte/video/1'];
        yield 'Instagram' => ['https://www.instagram.com/reel/abc/'];
        yield 'Facebook' => ['https://www.facebook.com/watch/?v=1'];
        yield 'Facebook court' => ['https://fb.watch/abc/'];
        yield 'Twitch' => ['https://www.twitch.tv/videos/1'];
        yield 'Wistia' => ['https://demo.wistia.com/medias/abc'];
        yield 'Vidyard' => ['https://share.vidyard.com/watch/abc'];
        yield 'Loom' => ['https://www.loom.com/share/abc'];
        yield 'Casse mélangée' => ['https://WWW.YouTube.COM/watch?v=xzaxUF8Wsvc'];
    }

    #[DataProvider('hebergeursRefuses')]
    public function testRefuseLesAutresDomaines(string $url): void
    {
        $this->validator->validate($url, new LienVideo());
        $this->buildViolation((new LienVideo())->message)
            ->setParameter(
                '{{ hebergeurs }}',
                implode(', ', array_keys(LienVideo::HEBERGEURS)),
            )
            ->assertRaised();
    }

    /** @return iterable<string, array{string}> */
    public static function hebergeursRefuses(): iterable
    {
        yield 'Site quelconque' => ['https://www.example.com/video.mp4'];
        yield 'Domaine imitant YouTube' => ['https://fakeyoutube.com/watch?v=1'];
        yield 'Suffixe trompeur' => ['https://youtube.com.evil.example/watch'];
        yield 'Pas une URL' => ['pas-une-url'];
    }

    public function testEstHebergeurAutorise(): void
    {
        self::assertTrue(LienVideoValidator::estHebergeurAutorise('https://youtu.be/abc'));
        self::assertFalse(LienVideoValidator::estHebergeurAutorise('https://example.com/abc'));
        self::assertFalse(LienVideoValidator::estHebergeurAutorise('sans-host'));
    }
}
