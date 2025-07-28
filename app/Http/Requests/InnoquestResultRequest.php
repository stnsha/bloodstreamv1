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
            'SendingFacility' => 'required|string',
            'MessageControlID' => 'required|string',

            'patient.PatientID' => 'nullable|string',
            'patient.PatientExternalID' => 'nullable|string', //refid?
            'patient.AlternatePatientID' => 'required|string', //icno
            'patient.PatientLastName' => 'required|string', //name
            'patient.PatientDOB' => 'required|string', //dob
            'patient.PatientGender' => 'required|string', //gender

            'patient.PatientFirstName' => 'nullable|string',
            'patient.PatientMiddleName' => 'nullable|string',
            'patient.PatientAddress' => 'nullable|string',
            'patient.PatientNationality' => 'nullable|string',

            'Orders' => 'required|array',
            'Orders.*.PlacerOrderNumber' => 'nullable|string',
            'Orders.*.FillerOrderNumber' => 'nullable|string',
            'Orders.*.PlacerGroupNumber' => 'nullable|string',
            'Orders.*.Status' => 'nullable|string',
            'Orders.*.Quantity' => 'nullable|string',
            'Orders.*.TransactionDateTime' => 'nullable|string',

            'Orders.*.OrderingProvider' => 'required|array',
            'Orders.*.OrderingProvider.Code' => 'required|string',
            'Orders.*.OrderingProvider.Name' => 'required|string',

            'Orders.*.Organization' => 'nullable|string',

            'Orders.*.Observations' => 'required|array',
            'Orders.*.Observations.*.PlacerOrderNumber' => 'nullable|string', //refid?
            'Orders.*.Observations.*.FillerOrderNumber' => 'required|string', //labno
            'Orders.*.Observations.*.ProcedureCode' => 'required|string', //panel code
            'Orders.*.Observations.*.ProcedureDescription' => 'required|string', //panel name
            'Orders.*.Observations.*.PackageCode' => 'nullable|string', //profile
            'Orders.*.Observations.*.Priority' => 'nullable|string',
            'Orders.*.Observations.*.RequestedDateTime' => 'nullable|string', //collected date
            'Orders.*.Observations.*.StartDateTime' => 'nullable|string', //received date
            'Orders.*.Observations.*.EndDateTime' => 'nullable|string', //reported date?
            'Orders.*.Observations.*.ClinicalInformation' => 'nullable|string', //panel test notes?
            'Orders.*.Observations.*.SpecimenDateTime' => 'nullable|string', //reported date?s

            'Orders.*.Observations.*.OrderingProvider' => 'required|array',
            'Orders.*.Observations.*.OrderingProvider.Code' => 'required|string', //doctor code
            'Orders.*.Observations.*.OrderingProvider.Name' => 'required|string', //doctor name

            'Orders.*.Observations.*.ResultStatus' => 'required|string', //panel result status
            'Orders.*.Observations.*.ServiceDateTime' => 'required|string',
            'Orders.*.Observations.*.ResultPriority' => 'required|string',

            'Orders.*.Observations.*.Results' => 'required|array',
            'Orders.*.Observations.*.Results.*.ID' => 'required|string', //ordinal id
            'Orders.*.Observations.*.Results.*.Type' => 'required|string', //type
            'Orders.*.Observations.*.Results.*.Identifier' => 'required|string', //identifier
            'Orders.*.Observations.*.Results.*.Text' => 'nullable|string',
            'Orders.*.Observations.*.Results.*.CodingSystem' => 'required|string',
            'Orders.*.Observations.*.Results.*.Value' => 'required|string',
            'Orders.*.Observations.*.Results.*.Units' => 'nullable|string',
            'Orders.*.Observations.*.Results.*.ReferenceRange' => 'nullable|string',
            'Orders.*.Observations.*.Results.*.Flags' => 'nullable|string',
            'Orders.*.Observations.*.Results.*.Status' => 'nullable|string', //actual result status
            'Orders.*.Observations.*.Results.*.ObservationDateTime' => 'nullable|string',

            'EncodedBase64pdf' => 'nullable|string'
        ];
    }
}
