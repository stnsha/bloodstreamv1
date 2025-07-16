<?php

namespace Database\Seeders;

use App\Models\HL7Library;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HL7LibrarySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ['value' => 'VS', 'description' => 'Very susceptible. Indicates for microbiology susceptibilities only', 'code' => '0078'],
            ['value' => 'W', 'description' => 'Worse-use when direction not relevant', 'code' => '0078'],
            ['value' => 'AA', 'description' => 'Very abnormal (applies to non-numeric results (analogous to panic limits for numeric units))', 'code' => '0078'],
            ['value' => 'B', 'description' => 'Better-use when direction not relevant', 'code' => '0078'],
            ['value' => 'D', 'description' => 'Significant change down', 'code' => '0078'],
            ['value' => 'H', 'description' => 'Above high normal', 'code' => '0078'],
            ['value' => 'HH', 'description' => 'Above upper panic limits', 'code' => '0078'],
            ['value' => 'I', 'description' => 'Intermediate. Indicates for microbiology susceptibilities only', 'code' => '0078'],
            ['value' => 'L', 'description' => 'Below low normal', 'code' => '0078'],
            ['value' => 'LL', 'description' => 'Below lower panic limits', 'code' => '0078'],
            ['value' => 'MS', 'description' => 'Moderately susceptible. Indicates for microbiology susceptibilities only', 'code' => '0078'],
            ['value' => 'N', 'description' => 'Normal (applies to non-numeric results)', 'code' => '0078'],
            ['value' => 'null', 'description' => "No range defined, or normal ranges don't apply", 'code' => '0078'],
            ['value' => 'R', 'description' => 'Resistant. Indicates for microbiology susceptibilities only', 'code' => '0078'],
            ['value' => 'S', 'description' => 'Susceptible. Indicates for microbiology susceptibilities only', 'code' => '0078'],
            ['value' => 'U', 'description' => 'Significant change up', 'code' => '0078'],
            ['value' => '<', 'description' => 'Below absolute low-off instrument scale', 'code' => '0078'],
            ['value' => '>', 'description' => 'Above absolute high-off instrument scale', 'code' => '0078'],
            ['value' => 'A', 'description' => 'Abnormal (applies to non-numeric results)', 'code' => '0078'],
            ['value' => 'C', 'description' => 'Record coming over is a correction and thus replaces a final result', 'code' => '0085'],
            ['value' => 'D', 'description' => 'Deletes the OBX record', 'code' => '0085'],
            ['value' => 'F', 'description' => 'Final results; Can only be changed with a corrected result.', 'code' => '0085'],
            ['value' => 'I', 'description' => 'Specimen in lab; results pending', 'code' => '0085'],
            ['value' => 'N', 'description' => 'Not asked; used to affirmatively document that the observation identified in the OBX was not sought when the universal service ID in OBR-4 implies that it would be sought.', 'code' => '0085'],
            ['value' => 'O', 'description' => 'Order detail description only (no result)', 'code' => '0085'],
            ['value' => 'P', 'description' => 'Preliminary results', 'code' => '0085'],
            ['value' => 'R', 'description' => 'Results entered -- not verified', 'code' => '0085'],
            ['value' => 'S', 'description' => 'Partial results. Deprecated. Retained only for backward compatibility as of V2.6.', 'code' => '0085'],
            ['value' => 'U', 'description' => 'Results status change to final without retransmitting results already sent as \'preliminary.\' E.g., radiology changes status from preliminary to final', 'code' => '0085'],
            ['value' => 'W', 'description' => 'Post original as wrong, e.g., transmitted for wrong patient', 'code' => '0085'],
            ['value' => 'X', 'description' => 'Results cannot be obtained for this observation', 'code' => '0085'],
            ['value' => 'AD', 'description' => 'Address', 'code' => '0125'],
            ['value' => 'CF', 'description' => 'Coded Element With Formatted Values', 'code' => '0125'],
            ['value' => 'CK', 'description' => 'Composite ID With Check Digit', 'code' => '0125'],
            ['value' => 'CN', 'description' => 'Composite ID And Name', 'code' => '0125'],
            ['value' => 'CP', 'description' => 'Composite Price', 'code' => '0125'],
            ['value' => 'CWE', 'description' => 'Coded Entry', 'code' => '0125'],
            ['value' => 'CX', 'description' => 'Extended Composite ID With Check Digit', 'code' => '0125'],
            ['value' => 'DT', 'description' => 'Date', 'code' => '0125'],
            ['value' => 'DTM', 'description' => 'Time Stamp (Date & Time)', 'code' => '0125'],
            ['value' => 'ED', 'description' => 'Encapsulated Data', 'code' => '0125'],
            ['value' => 'FT', 'description' => 'Formatted Text (Display)', 'code' => '0125'],
            ['value' => 'MO', 'description' => 'Money', 'code' => '0125'],
            ['value' => 'NM', 'description' => 'Numeric', 'code' => '0125'],
            ['value' => 'PN', 'description' => 'Person Name', 'code' => '0125'],
            ['value' => 'RP', 'description' => 'Reference Pointer', 'code' => '0125'],
            ['value' => 'SN', 'description' => 'Structured Numeric', 'code' => '0125'],
            ['value' => 'ST', 'description' => 'String Data.', 'code' => '0125'],
            ['value' => 'TM', 'description' => 'Time', 'code' => '0125'],
            ['value' => 'TN', 'description' => 'Telephone Number', 'code' => '0125'],
            ['value' => 'TX', 'description' => 'Text Data (Display)', 'code' => '0125'],
            ['value' => 'XAD', 'description' => 'Extended Address', 'code' => '0125'],
            ['value' => 'XCN', 'description' => 'Extended Composite Name And Number For Persons', 'code' => '0125'],
            ['value' => 'XON', 'description' => 'Extended Composite Name And Number For Organizations', 'code' => '0125'],
            ['value' => 'XPN', 'description' => 'Extended Person Name', 'code' => '0125'],
            ['value' => 'XTN', 'description' => 'Extended Telecommunications Number', 'code' => '0125'],
            ['value' => 'A', 'description' => 'Ambiguous', 'code' => '0001'],
            ['value' => 'F', 'description' => 'Female', 'code' => '0001'],
            ['value' => 'M', 'description' => 'Male', 'code' => '0001'],
            ['value' => 'N', 'description' => 'Not applicable', 'code' => '0001'],
            ['value' => 'O', 'description' => 'Other', 'code' => '0001'],
            ['value' => 'U', 'description' => 'Unknown', 'code' => '0001']
        ];

        foreach ($data as $item) {
            HL7Library::firstOrCreate(
                ['value' => $item['value'], 'code' => $item['code']],
                ['description' => $item['description']]
            );
        }
    }
}
