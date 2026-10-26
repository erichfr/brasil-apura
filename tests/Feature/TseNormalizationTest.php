<?php

namespace Tests\Feature;

use App\Services\Tse\TseException;
use App\Services\Tse\TseResultsNormalizer;
use Tests\Support\TseFixture;
use Tests\TestCase;

class TseNormalizationTest extends TestCase
{
    private function normalize(array $data): array
    {
        return (new TseResultsNormalizer)->normalize(json_encode($data, JSON_THROW_ON_ERROR), 2, 'br');
    }

    public function test_totalization_generation_and_collection_are_not_confused(): void
    {
        $data = $this->normalize(TseFixture::result());
        $this->assertSame('2026-10-25T22:00:00+00:00', $data['snapshot']['source_reported_at']->toIso8601String());
        $this->assertSame('2026-10-25T22:00:01+00:00', $data['snapshot']['source_generated_at']->toIso8601String());
        $this->assertArrayNotHasKey('collected_at', $data['snapshot']);
    }

    public function test_simulated_results_are_rejected(): void
    {
        $this->expectException(TseException::class);
        $this->normalize(TseFixture::result(['f' => 's']));
    }

    public function test_wrong_round_or_scope_is_rejected(): void
    {
        $this->expectException(TseException::class);
        $this->normalize(TseFixture::result(['cdabr' => 'sp']));
    }

    public function test_other_offices_are_rejected(): void
    {
        $this->expectException(TseException::class);
        $this->normalize(TseFixture::result(['carg' => [['cd' => '3']]]));
    }

    public function test_invalid_calendar_dates_are_rejected(): void
    {
        $this->expectException(TseException::class);
        $this->normalize(TseFixture::result(['dt' => '31/02/2026']));
    }

    public function test_missing_turnout_is_unknown_instead_of_zero(): void
    {
        $fixture = TseFixture::result();
        unset($fixture['e']['c']);
        $data = $this->normalize($fixture);
        $this->assertNull($data['snapshot']['turnout']);
    }

    public function test_withheld_votes_are_not_published_as_observed_zeros(): void
    {
        $data = $this->normalize(TseFixture::result(['dv' => 'n']));
        $this->assertFalse($data['snapshot']['votes_disclosed']);
        $this->assertNull($data['snapshot']['total_votes']);
        $this->assertSame([], $data['candidates']);
    }

    public function test_valid_vote_percentage_uses_valid_votes_not_all_competitors(): void
    {
        $fixture = TseFixture::result(['v' => ['tv' => '120', 'vvc' => '100']]);
        $fixture['carg'][0]['agr'][0]['par'][0]['cand'][0]['pvapn'] = '80';
        $data = $this->normalize($fixture);
        $this->assertSame('100.0000', $data['candidates'][0]['valid_votes_percentage']);
        $this->assertSame('80', $data['candidates'][0]['official_metadata']['official_percentage']);
        $this->assertSame('v.vvc', $data['candidates'][0]['official_metadata']['official_percentage_denominator']);
    }

    public function test_annulled_votes_are_not_given_a_valid_vote_percentage(): void
    {
        $fixture = TseFixture::result();
        $fixture['carg'][0]['agr'][0]['par'][0]['cand'][0]['dvt'] = 'Anulado sub judice';
        $data = $this->normalize($fixture);
        $this->assertNull($data['candidates'][0]['valid_votes_percentage']);
        $this->assertSame(80, $data['candidates'][0]['votes']);
    }

    public function test_negative_votes_are_rejected(): void
    {
        $fixture = TseFixture::result();
        $fixture['carg'][0]['agr'][0]['par'][0]['cand'][0]['vap'] = '-1';
        $this->expectException(TseException::class);
        $this->normalize($fixture);
    }
}
