<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Translation;
use Artisan;
use Cache;
use CoreComponentRepository;
use Illuminate\Support\Facades\Auth;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Throwable;

class AdminController extends Controller
{
    /**
     * Show the admin dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function admin_dashboard(Request $request)
    {
        // CoreComponentRepository::initializeCache();
        // $user = Auth::user();
        // $user->assignRole('Super Admin');
        $root_categories = Category::where('level', 0)->get();

        $cached_graph_data = Cache::remember('cached_graph_data', 86400, function () use ($root_categories) {
            $num_of_sale_data = null;
            $qty_data = null;
            foreach ($root_categories as $key => $category) {
                $category_ids = \App\Utility\CategoryUtility::children_ids($category->id);
                $category_ids[] = $category->id;

                $products = Product::with('stocks')->whereIn('category_id', $category_ids)->get();
                $qty = 0;
                $sale = 0;
                foreach ($products as $key => $product) {
                    $sale += $product->num_of_sale;
                    foreach ($product->stocks as $key => $stock) {
                        $qty += $stock->qty;
                    }
                }
                $qty_data .= $qty . ',';
                $num_of_sale_data .= $sale . ',';
            }
            $item['num_of_sale_data'] = $num_of_sale_data;
            $item['qty_data'] = $qty_data;

            return $item;
        });

        return view('backend.dashboard', compact('root_categories', 'cached_graph_data'));
    }

    function clearCache(Request $request)
    {
        Artisan::call('optimize:clear');
        flash(translate('Cache cleared successfully'))->success();
        return back();
    }



    public function updateArabicTranslations()
    {
        try {
            ini_set('max_execution_time', 500);
            set_time_limit(0);
            $translator = new GoogleTranslate('ar');
            // dd(Translation::query()->whereLang('sa')->delete());
            Translation::query()->whereLang('en')->chunk(200, function ($words) use ($translator) {
                foreach ($words as $word) {
                    Translation::query()->create([
                        'lang_key' => $word->lang_key,
                        'lang_value' => $translator->translate($word->lang_value),
                        'lang' => 'sa',
                    ]);
                }
            });
        } catch (Throwable $e) {
            dd($e);
        }
        dd('Done Translations');
    }
}
