<x-app-layout>
    <x-slot name="header">{{ __('Normativas') }}</x-slot>

    <div class="space-y-6">
        <x-header
            title="Normativas"
            subtitle="Documentos legales, académicos e institucionales de la Universidad Nacional del Este."
            separator
        />

        <x-card class="glass-card">
            <div class="mb-4">
                <h2 class="text-lg font-semibold">
                    Normativa legal de la UNE
                </h2>

                <p class="text-sm text-base-content/70">
                    En esta sección se disponibilizan documentos oficiales en formato PDF para consulta de los estudiantes.
                </p>
            </div>

            <div class="space-y-3">
                @foreach ($secciones as $index => $seccion)
                    <x-collapse separator collapse-plus-minus :open="$index === 0">
                        <x-slot:heading>
                            <div>
                                <div class="font-semibold">
                                    {{ $seccion['titulo'] }}
                                </div>

                                @isset($seccion['descripcion'])
                                    <div class="text-xs text-base-content/60">
                                        {{ $seccion['descripcion'] }}
                                    </div>
                                @endisset
                            </div>
                        </x-slot:heading>

                        <x-slot:content>
                            <div class="space-y-3">
                                @foreach ($seccion['items'] as $item)
                                    @php
                                        $rutaArchivo = $item['archivo'] ?? null;
                                        $archivoFisico = $rutaArchivo ? public_path($rutaArchivo) : null;
                                        $archivoExiste = $archivoFisico && is_file($archivoFisico);
                                    @endphp

                                    <div class="flex flex-col gap-3 rounded-lg border border-base-300 p-4 md:flex-row md:items-center md:justify-between">
                                        <div>
                                            <h3 class="font-medium">
                                                {{ $item['titulo'] }}
                                            </h3>

                                            @isset($item['descripcion'])
                                                <p class="text-sm text-base-content/70">
                                                    {{ $item['descripcion'] }}
                                                </p>
                                            @endisset

                                            @unless ($archivoExiste)
                                                <p class="mt-1 text-xs font-semibold text-warning">
                                                    {{ __('Documento pendiente de carga.') }}
                                                </p>
                                            @endunless
                                        </div>

                                        <div class="flex gap-2">
                                            @if ($archivoExiste)
                                                <x-button
                                                    label="Ver PDF"
                                                    icon="o-eye"
                                                    link="{{ asset($rutaArchivo) }}"
                                                    external
                                                    class="btn-primary btn-sm"
                                                />

                                                <x-button
                                                    label="Descargar"
                                                    icon="o-arrow-down-tray"
                                                    link="{{ asset($rutaArchivo) }}"
                                                    external
                                                    class="btn-outline btn-sm"
                                                />
                                            @else
                                                <x-button
                                                    label="No disponible"
                                                    icon="o-clock"
                                                    class="btn-ghost btn-sm"
                                                    disabled
                                                />
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </x-slot:content>
                    </x-collapse>
                @endforeach
            </div>
        </x-card>
    </div>
</x-app-layout>
