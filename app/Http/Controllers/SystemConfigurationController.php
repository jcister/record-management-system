<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSystemConfigurationRequest;
use App\Models\SystemConfiguration;
use Inertia\Inertia;
use Inertia\Response;

class SystemConfigurationController extends Controller
{
    /**
     * Display the system configuration page.
     */
    public function index(): Response
    {
        $config = SystemConfiguration::getInstance();

        return Inertia::render('systemsettings/SystemConfiguration', [
            'config' => [
                'agency_name' => $config->agency_name,
                'telephone' => $config->telephone,
                'address' => $config->address,
                'regional_director_name' => $config->regional_director_name,
                'regional_director_designation' => $config->regional_director_designation,
                'updated_at' => $config->updated_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Display the lookup management page.
     */
    public function lookupManagement(): Response
    {
        return Inertia::render('systemsettings/LookupManagement');
    }

    /**
     * Update the system configuration.
     */
    public function update(UpdateSystemConfigurationRequest $request): \Illuminate\Http\RedirectResponse
    {
        $config = SystemConfiguration::getInstance();

        $config->update($request->validated());

        return redirect()
            ->back()
            ->with('success', 'System configuration updated successfully.');
    }
}
