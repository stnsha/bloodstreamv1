<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property string $SendingFacility 
 * @property string $MessageControlID
 * @property string $PatientExternalID
 * @property string $AlternatePatientID
 * @property string $PatientLastName
 * @property string $PatientDOB
 * @property string $PatientGender
 * @property array $observations
 * @property string $PlacerOrderNumber
 * @property string $FillerOrderNumber
 * @property string $ProcedureCode
 * @property string $ProcedureDescription
 * @property string $PackageCode
 * @property string $RequestedDateTime
 * @property string $StartDateTime
 * @property string $EndDateTime
 * @property string $ClinicalInformation
 * @property string $SpecimenDateTime
 * @property array $OrderingProvider
 * @property string $Code
 * @property string $Name
 * @property string $ResultStatus
 * @property string $ID
 * @property string $Type
 * @property string $Identifier
 * @property string $Text
 * @property string $Value
 * @property string $Units
 * @property string $ReferenceRange
 * @property string $Flags
 * @property string $Status
 * @property string $ObservationDateTime
 */
class InnoquestResultRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Root Level - Always Expected
            'SendingFacility' => 'required|string',
            'MessageControlID' => 'required|string',

            // Patient Information
            'patient' => 'required|array',
            'patient.PatientID' => 'nullable|string', // Optional (MRN)
            'patient.PatientExternalID' => 'nullable|string', // not used
            'patient.AlternatePatientID' => 'nullable|string', // Optional (NRIC)
            'patient.PatientLastName' => 'required|string', // Always Expected (Full Name)
            'patient.PatientFirstName' => 'nullable|string', // not used
            'patient.PatientMiddleName' => 'nullable|string', // not used
            'patient.PatientDOB' => 'required|string', // Always Expected (YYYYMMDD)
            'patient.PatientGender' => 'required|string|in:M,F', // Always Expected (M/F)
            'patient.PatientAddress' => 'nullable|string', // Optional
            'patient.PatientNationality' => 'nullable|string', // Optional

            // Orders
            'Orders' => 'required|array|min:1',
            'Orders.*.PlacerOrderNumber' => 'nullable|string', // Optional (Client Order Number)
            'Orders.*.FillerOrderNumber' => 'required|string', // Always Expected (IQMY Request Number)
            'Orders.*.PlacerGroupNumber' => 'nullable|string', // not used
            'Orders.*.Status' => 'nullable|string',
            'Orders.*.Quantity' => 'nullable|string', // not used
            'Orders.*.TransactionDateTime' => 'nullable|string',
            'Orders.*.Organization' => 'nullable|string', // not used

            // Ordering Provider - Always Expected
            'Orders.*.OrderingProvider' => 'required|array',
            'Orders.*.OrderingProvider.Code' => 'required|string', // Always Expected (IQMY Doctor Code)
            'Orders.*.OrderingProvider.Name' => 'required|string', // Always Expected (Doctor Name)

            // Observations
            'Orders.*.Observations' => 'required|array|min:1',
            'Orders.*.Observations.*.PlacerOrderNumber' => 'nullable|string', // Optional (Client Order Number)
            'Orders.*.Observations.*.FillerOrderNumber' => 'required|string', // Always Expected (IQMY Request Number)
            'Orders.*.Observations.*.ProcedureCode' => 'required|string', // Always Expected (Testing Panel Code)
            'Orders.*.Observations.*.ProcedureDescription' => 'required|string', // Always Expected (Panel Description)
            'Orders.*.Observations.*.PackageCode' => 'nullable|string', // Optional (Package code)
            'Orders.*.Observations.*.Priority' => 'nullable|string', // Optional (Test Priority Flag)
            'Orders.*.Observations.*.RequestedDateTime' => 'nullable|string', // Optional
            'Orders.*.Observations.*.StartDateTime' => 'nullable|string', // Optional
            'Orders.*.Observations.*.EndDateTime' => 'nullable|string', // Optional
            'Orders.*.Observations.*.ClinicalInformation' => 'nullable|string', // Optional
            'Orders.*.Observations.*.SpecimenDateTime' => 'nullable|string', // Optional

            // Observation Ordering Provider - Always Expected
            'Orders.*.Observations.*.OrderingProvider' => 'nullable|array',
            'Orders.*.Observations.*.OrderingProvider.Code' => 'nullable|string', // Always Expected (IQMY Doctor Code)
            'Orders.*.Observations.*.OrderingProvider.Name' => 'nullable|string', // Always Expected (Doctor Name)

            'Orders.*.Observations.*.ResultStatus' => 'required|string', // Always Expected
            'Orders.*.Observations.*.ServiceDateTime' => 'required|string', // Always Expected
            'Orders.*.Observations.*.ResultPriority' => 'required|string', // Always Expected

            // Results
            'Orders.*.Observations.*.Results' => 'required|array|min:1',
            'Orders.*.Observations.*.Results.*.ID' => 'required|string', // Always Expected (ordinal id)
            'Orders.*.Observations.*.Results.*.Type' => 'required|string', // Always Expected
            'Orders.*.Observations.*.Results.*.Identifier' => 'required|string', // Always Expected
            'Orders.*.Observations.*.Results.*.Text' => 'nullable|string', // Optional
            'Orders.*.Observations.*.Results.*.CodingSystem' => 'required|string', // Always Expected
            'Orders.*.Observations.*.Results.*.Value' => 'required|string', // Always Expected
            'Orders.*.Observations.*.Results.*.Units' => 'nullable|string', // Optional
            'Orders.*.Observations.*.Results.*.ReferenceRange' => 'nullable|string', // Optional
            'Orders.*.Observations.*.Results.*.Flags' => 'nullable|string', // Optional
            'Orders.*.Observations.*.Results.*.Status' => 'required|string', // Always Expected
            'Orders.*.Observations.*.Results.*.ObservationDateTime' => 'required|string', // Always Expected

            // Additional field
            'EncodedBase64pdf' => 'nullable|string'
        ];
    }
}
