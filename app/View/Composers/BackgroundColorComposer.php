<?php

namespace App\View\Composers;

use Illuminate\View\View;
use App\Models\ColorSitting;

class BackgroundColorComposer
{
    /**
     * Bind data to the view.
     *
     * @param  \Illuminate\View\View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $backgroundColor = ColorSitting::first();
        $backgroundColorr = $backgroundColor->color ?? '#ffffff';
        
        $view->with('backgroundColorr', $backgroundColorr);
    }
}
