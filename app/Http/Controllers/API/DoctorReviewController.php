<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TestResult;
use App\Models\ResultLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DoctorReviewController extends Controller
{
    public function index()
    {
        $testResults = TestResult::with([
            'patient',
            'testResultItems.panelPanelItem.panel.panelCategory',
            'testResultItems.referenceRange',
            'testResultItems.panelPanelItem.panelItem',
            'testResultItems.panelComments.masterPanelComment',
        ])
        ->where('id', 49)
        ->where('is_completed', true)
        ->get();

        $result = [];
        foreach($testResults as $tr) {
            $patientInfo = [
                'patient_age' => $tr->patient->age,
                'patient_gender' => $tr->patient->gender,
            ];

            $categorizedItems = [];
            
            foreach($tr->testResultItems as $ri) {
                $categoryName = $ri->panelPanelItem->panel->panelCategory->name ?? $ri->panelPanelItem->panel->name;
                
                if (!isset($categorizedItems[$categoryName])) {
                    $categorizedItems[$categoryName] = [];
                }
                
                $flagDescription = $ri->flag;
                if ($ri->flag) {
                    $resultLibrary = ResultLibrary::where('code', '0078')
                        ->where('value', $ri->flag)
                        ->first();
                    $flagDescription = $resultLibrary ? $resultLibrary->description : $ri->flag;
                }
                
                $itemData = [
                    'panel_item_name' => $ri->panelPanelItem->panelItem->name,
                    'value' => $ri->value,
                    'unit' => $ri->panelPanelItem->panelItem->unit,
                    'flag' => $flagDescription,
                    'reference_range' => $ri->reference_range_id != null ? $ri->referenceRange->value : null,
                    'comments' => []
                ]; 
                
                foreach($ri->panelComments as $pc) {
                    $itemData['comments'][] = $pc->masterPanelComment->comment;
                }
                
                $categorizedItems[$categoryName][] = $itemData;
            }

            $result[] = [
                'patient_info' => $patientInfo,
                'blood_test_results' => $categorizedItems
            ];
        }

        // Send to OpenAI
        $openaiAnalysis = $this->sendToOpenAI($result);
        dd($openaiAnalysis);

        // return response()->json($result);
    }

    private function sendToOpenAI($results)
    {
        $apiKey = env('OPENAI_API_KEY');

        $systemPrompt = "You are an experienced doctor in Malaysia. Generate a structured Blood Test Summary Report with two sections: **Summaries** and **Plans & Recommendations**. Follow these rules strictly:\n
        1. Use **numbered lists (1., 2., 3.)** for all points. Do NOT use bullet points, asterisks, or dashes.\n
        2. Use a **neutral and professional tone**. Instead of \"Your report shows...\", use:\n
        - \"The lab results indicate...\"\n
        - \"Findings from the lab report suggest...\"\n
        - \"The test results reveal...\"\n
        - \"Analysis of the report shows...\"\n\n
        3. Compare test results to the following standard reference values when applicable:\n\n
        4. Do not display value. Just observations.\n
        5. DO NOT use symbols. Instead, use words like 'more than', 'less than' etc.\n
        6. DO NOT and avoid use superscripts and subscripts for reference range. Use words if applicable or none.\n\n

        **LDL-C Target Levels**:\n
        - **Low CV Risk**: <3.0 mmol/L\n
        - **Moderate CV Risk**: <2.6 mmol/L\n
        - **High CV Risk**: <=1.8 mmol/L\n
        - **Very High CV Risk**: <=1.4 mmol/L\n
        - **Recurrent CV events within 2 years**: <1.0 mmol/L\n\n

        **Non-HDL-C Target Levels**:\n
        - **Low CV Risk**: <3.8 mmol/L\n
        - **Moderate CV Risk**: <3.4 mmol/L\n
        - **High CV Risk**: <=2.6 mmol/L\n
        - **Very High CV Risk**: <=2.2 mmol/L\n\n

        **Total Cholesterol Target Levels**:\n
        - **Men**: CV risk if >5.0 mmol/L, target to reduce <4.5 mmol/L\n\n

        **HDL-c Ratio (Castelli I)**:\n
        - **Women**: CV risk if >4.5, target to reduce <4.0\n\n

        **LDL-c/HDL-c Ratio (Castelli II)**:\n
        - **Men**: CV risk if >3.5, target to reduce <3.0\n
        - **Women**: CV risk if >3.0, target to reduce <2.5\n\n

        **Atherogenic Index in Plasma (AIP) Risk Categories**:\n
        - **Low risk**: <0.10\n
        - **Intermediate risk**: 0.10 - 0.24\n
        - **High risk**: >0.24\n\n

        **Fasting Plasma Glucose (FPG) Interpretation**:\n
        - **Normal**: 3.9 - 6.0 mmol/L\n
        - **IFG (Prediabetes)**: 6.1 - 6.9 mmol/L\n
        - **DM (Diabetes Mellitus)**: >=7.0 mmol/L\n
        - *Recommend Oral Glucose Tolerance Test (OGTT) for fasting plasma glucose levels 6.1 - 6.9 mmol/L.*\n\n

        **Diagnostic Values of HbA1c in Malaysian Adults**:\n
        - **HbA1c (NGSP) < 5.7%** / **HbA1c (IFCC) < 39 mmol/mol** → Normal\n
        - **HbA1c (NGSP) 5.7 - 6.2%** / **HbA1c (IFCC) 39 - 44 mmol/mol** → *Prediabetes (IFG or IGT)*\n
        - **HbA1c (NGSP) >= 6.3%** / **HbA1c (IFCC) >= 45 mmol/mol** → *Diabetes (T2DM)*\n
        - *Recommend OGTT for HbA1c levels 5.7 - 6.2%*\n
        - **Important:** HbA1c **>=6.3% is already diabetes**. Patients **with HbA1c >=6.5% require referral**.\n\n

        **Stages of Chronic Kidney Disease (CKD) Based on GFR**:\n
        - **Stage 1**: Normal kidney function (GFR >=90)\n
        - **Stage 2**: Mild loss of kidney function (GFR 89-60)\n
        - **Stage 3a**: Mild to moderate loss of kidney function (GFR 59-45)\n
        - **Stage 3b**: Moderate to severe loss of kidney function (GFR 44-30)\n
        - **Stage 4**: Severe loss of kidney function (GFR 29-15)\n
        - **Stage 5**: Kidney failure (GFR <15)\n\n

        **Individualised HbA1c Target for Known Diabetes**:\n
        - **HbA1c (NGSP) <= 6.5%** / **HbA1c (IFCC) <= 48 mmol/mol** → A: Tight target for young, newly diagnosed diabetes without hypoglycaemia.\n
        - **HbA1c (NGSP) 6.6 - 7.0%** / **HbA1c (IFCC) 49 - 53 mmol/mol** → B: Target for all other individuals not in category A or C.\n
        - **HbA1c (NGSP) 7.1 - 8.0%** / **HbA1c (IFCC) 54 - 64 mmol/mol** → C: Target for diabetes with comorbidities, short life expectancy, or hypoglycaemia risk.\n\n

        **Additional Considerations**:\n
        - Microcytic hypochromic anemia suggests the need to rule out iron deficiency or thalassemia. A full iron profile should be performed, and if normal, hemoglobin electrophoresis is recommended.\n
        - Markedly raised potassium levels with lab hemolysis remarks should prompt a repeat test, particularly if potassium is significantly elevated (e.g., 6.8 mmol/L).\n
        - Urine nitrite positivity should be evaluated for possible urinary tract infections.\n
        - Low white blood cell count should prompt an assessment for infections or inflammation.\n
        - Lipid profile should be interpreted as a whole, considering total cholesterol and LDL levels together.\n
        - Raised erythrocyte sedimentation rate (ESR) should be mentioned and clinically assessed.\n
        - Low HDL should be highlighted as it contributes to cardiovascular risk.\n
        - Markedly deranged liver function should lead to a referral to a doctor instead of simple follow-up advice.\n
        - Normal urine test results should be explicitly mentioned for reassurance.\n\n
        
        4. Format the response as follows:\n\n

        **Blood Test Summary Report**\n\n

        **Summaries**\n
        - [First summary point, e.g., compare results to reference ranges using the values above].\n
        - [Second summary point, e.g., brief all normal parameters. If result value is null, skip.].\n
        - [Third summary point, e.g., brief any abnormal parameters. If result value is null, skip.].\n
        - [Additional summary points as needed...]\n
        - Do not include two different panel items in one sentence. Make each a separate point.\n
        - Must mention kidney and liver functions.\n
        - Must mention renal profile.\n\n

        **Plans & Recommendations**\n
        1. [First actionable advice, e.g., dietary changes].\n
        2. [Second recommendation, e.g., medication like Powerlife Arespid if LDL is elevated].\n
        3. [Third step, e.g., referral for diabetes if HbA1c >=6.5%].\n\n

        Additional instructions:\n
        - **For Summaries**:\n
        - Highlight deviations from reference ranges without listing exact values.\n
        - Mention at least two normal parameters to reassure the patient.\n
        - Use phrases like “slightly elevated” or “mildly low,” and clarify risks for borderline results (e.g., prediabetes).\n\n

        - **For Plans & Recommendations**:\n
        - Prioritize lifestyle/diet advice for abnormal results.\n
        - Include Powerlife medications only when criteria are met (e.g., LDL > 2.58 mmol/L → Powerlife Arespid).\n
        - **Ensure referral for HbA1c >=6.5% as diabetes is confirmed.**\n
        - Advise clinical follow-up for liver abnormalities or infections.\n\n
        - Advise refer to a nutritionist for diet and lifestyle control.'\n\n

        - **Product suggestions**:\n
        -If LDL > 2.58 mmol/L, recommend Powerlife Arespid\n\n
        -If HbA1c > 5.8%, recommend Powerlife Diareso.\n\n
        -If Hepatitis B result exist, suggest vaccination for non-reactive Hepatitis B antibodies.\n\n

        - **Formatting**:\n
        - **Bold section headers** but use plain text for content.\n
        - Ensure every list item starts with a number (1., 2., 3.), not bullets.\n
        - Keep language professional yet easy to understand.
        - Keep it detailed and concise.";

        $promptEg = json_encode($results);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey,
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $promptEg]
            ],
            'temperature' => 0.2,  // Lower temperature ensures factual and structured responses
            'max_tokens' => 1000,  // Adjust as needed to control response length
            'top_p' => 0.5,  // Limits randomness, keeping responses relevant
            'frequency_penalty' => 0.4,  // Reduces repetitive words
            'presence_penalty' => 0.1   // Encourages varied wording without going off-topic
        ]);

        // Log::error('OpenAI API Response:', $response->json());

        if ($response->successful()) {
            $openaiResult = $response->json();
            $messageContent = $openaiResult['choices'][0]['message']['content'] ?? 'No response from AI';

            // Convert **bold** text to <strong>
            $messageContent = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $messageContent);

            // Ensure section titles and numbered lists are formatted correctly
            $formattedHtml = preg_replace([
                '/(<strong>.*?<\/strong>)/',   // Bold section titles
                '/(\d+)\.\s(?=[A-Za-z])/'      // Numbered lists (only when followed by a letter)
            ], [
                '<br><br>$1',  // Ensure section titles have extra space
                '<br>$1. '     // Ensure numbered lists start on a new line without breaking content
            ], $messageContent);

            return $formattedHtml;
        } else {
            Log::error('OpenAI API request failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        }
    }
}