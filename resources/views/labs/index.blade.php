<x-app-layout>
    <div class="flex flex-col justify-between items-start w-full">
        <span class="font-semibold text-md tracking-wide pb-2">Lab Information</span>
        <div class="relative overflow-x-auto shadow-sm w-full sm:rounded-lg">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500">
                <thead class="text-xs text-gray-950 uppercase bg-stone-75">
                    <tr>
                        <th scope="col" class="px-6 py-3">
                            Lab Name
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Code
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Path
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Action
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($labs as $lab)
                        <tr class="odd:bg-white even:bg-stone-75 border-b border-gray-200">

                            <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                                {{ $lab->name }}
                            </th>
                            <td class="px-6 py-4">
                                {{ $lab->code }}
                            </td>
                            <td class="px-6 py-4">
                                {{ $lab->path }}
                            </td>
                            <td class="px-6 py-4">
                                {{-- <a href="" type="button"
                                    class="text-white bg-orange-950 hover:bg-orange-900 focus:ring-4 focus:outline-none focus:ring-orange-300 font-normal rounded-lg text-sm sm:w-auto px-3 py-2.5 text-center">Sync
                                    File</a> --}}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
