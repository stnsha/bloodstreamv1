<x-app-layout>
    <div class="flex flex-col w-full pr-32">
        <div class="flex justify-start items-end mb-2">
            <img src="{{ asset('logo.svg') }}" class="w-8 h-8 opacity-80 mr-2" />
            <span class="font-semibold text-md tracking-wide">BloodStream v1</span>
        </div>
        <span class="font-normal text-sm text-justify tracking-wide mb-3">
            BloodStream is a centralized middleware
            system
            designed to
            act as a secure and customizable bridge
            between your organization and external laboratories. Its core function is to collect, normalize, and
            centralize patient blood test results, enabling healthcare professionals to review, analyze, and act
            upon laboratory findings efficiently.</span>
        <span class="font-semibold text-sm pb-1.5 tracking-wide">Key Features</span>
        <ul class="list-decimal pl-5">
            <li class="font-normal text-sm tracking-wide pb-1.5">Pulls blood test results from external lab APIs.</li>
            <li class="font-normal text-sm tracking-wide pb-1.5">Accepts results via custom POST APIs from external
                labs.
            </li>
            <li class="font-normal text-sm tracking-wide pb-1.5">Exposes endpoints for internal systems to retrieve test
                data.</li>
            <li class="font-normal text-sm tracking-wide pb-1.5">Supports custom integration workflows and flexible
                formatting.</li>
            <li class="font-normal text-sm tracking-wide pb-1.5">Ensures secure, structured data flow.</li>
        </ul>
        <span class="font-semibold text-sm pb-1.5 tracking-wide">Base URL</span>
        <span class="font-normal text-sm text-justify tracking-wide mb-3">This API is accessible via staging and
            production environments hosted on publicly reachable domains. No VPN is required to access these endpoints
            unless specifically requested by an external party for security reasons.</span>
        <ul class="list-disc pl-5 mb-2">
            <li class="font-normal text-sm tracking-wide pb-1.5">
                {{-- <span>Staging:</span> --}}
                <span class="font-mono text-green-700 text-sm">https://mytotalhealth.com.my/staging</span>
            </li>
            <li class="font-normal text-sm tracking-wide pb-1.5">
                {{-- <span>Production:</span> --}}
                <span class="font-mono text-green-700 text-sm">https://mytotalhealth.com.my/production</span>
            </li>
            <li class="font-normal text-sm tracking-wide pb-1.5">
                <span class="font-mono text-green-700 text-sm">/api/v1</span>
                <span>— the versioned API prefix</span>
            </li>
        </ul>
        <span class="font-normal text-sm text-justify tracking-wide mb-3">📌Note: These URLs are accessible over the
            internet. Ensure your system can make outbound HTTPS requests
            to the above domains. If an external lab requires a VPN or static IP whitelisting, that can be arranged on
            request.</span>
        <span class="font-semibold text-sm pb-1.5 tracking-wide">Authentication</span>
        <span class="font-normal text-sm text-justify tracking-wide mb-3">Each laboratory is assigned exactly one unique
            username and password, which are used to log in and generate
            a secure access token. After logging in, a JWT token is issued and must be included in the <span
                class="font-mono text-green-700 text-sm">Authorization</span>
            header of all subsequent API requests, using the format: <span
                class="font-mono text-green-700 text-sm">Authorization: Bearer <token></span>.
        </span>

    </div>
</x-app-layout>
