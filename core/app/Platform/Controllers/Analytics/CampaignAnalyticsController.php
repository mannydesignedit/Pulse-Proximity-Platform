<?php namespace Platform\Controllers\Analytics;

use \Platform\Controllers\Core;
use \Platform\Controllers\Analytics;
use \Platform\Models\Analytics as ModelAnalytics;
use \Platform\Models\Location;
use \Platform\Models\Campaigns;
use Illuminate\Http\Request;

class CampaignAnalyticsController extends \App\Http\Controllers\Controller {

  /*
   |--------------------------------------------------------------------------
   | Campaign Analytics Controller
   |--------------------------------------------------------------------------
   |
   | Campaign Analytics related logic
   |--------------------------------------------------------------------------
   */

  /**
   * Campaign Analytics
   */
  public function showAnalytics()
  {
    // Security link
    $sl = request()->get('sl', '');
    $sql_campaign = '1=1';
    $campaign_id = '';

    if ($sl != '') {
      $qs = Core\Secure::string2array($sl);
      $campaign_id = $qs['campaign_id'];
      $sql_campaign = 'campaign_id = ' . $campaign_id;
      $sl = rawurlencode($sl);
    }

    // Range
    $date_start = request()->get('start', date('Y-m-d', strtotime(' - 30 day')));
    $date_end = request()->get('end', date('Y-m-d'));

    $from =  $date_start . ' 00:00:00';
    $to = $date_end . ' 23:59:59';

    /*
     |--------------------------------------------------------------------------
     | Campaigns
     |--------------------------------------------------------------------------
     */
    $campaigns = Campaigns\Campaign::where('user_id', Core\Secure::userId())
      ->where('active', 1)
      ->orderBy('created_at', 'asc')
      ->get();

    /*
     |--------------------------------------------------------------------------
     | First date
     |--------------------------------------------------------------------------
     */
    $stats_found = false;
    $first_date = date('Y-m-d');

    // Card views
    // Raw query because of this issue: https://github.com/laravel/framework/issues/18523
    if (is_numeric($campaign_id)) {
      $stats_card_views = \DB::select("select DATE(created_at) as date
        from `card_stats` 
        where `user_id` = :user_id 
        and exists (select * from `campaigns` inner join `campaign_card` on `campaigns`.`id` = `campaign_card`.`campaign_id` where `campaign_card`.`campaign_id` in (:campaign_id))  
        order by date asc", 
      [
        'user_id' => Core\Secure::userId(),
        'campaign_id' => $campaign_id
      ]);
    } elseif(is_array($campaign_id)) {
      $campaign_ids = implode(',', $campaign_id);
      $stats_card_views = \DB::select("select DATE(created_at) as date
        from `card_stats` 
        where `user_id` = :user_id 
        and exists (select * from `campaigns` inner join `campaign_card` on `campaigns`.`id` = `campaign_card`.`campaign_id` where `campaign_card`.`campaign_id` in (:campaign_ids)) 
        order by date asc", 
      [
        'user_id' => Core\Secure::userId(),
        'campaign_ids' => $campaign_ids
      ]);
    }  else {
      $stats_card_views = \DB::select("select DATE(created_at) as date
        from `card_stats` 
        where `user_id` = :user_id 
        order by date asc", 
      [
        'user_id' => Core\Secure::userId()
      ]);
    }

    if (! empty($card_views_stats)) {
      $stats_found = true;
      if ($card_views_stats->date < $first_date) $first_date = $card_views_stats->date;
    }

    // Interactions
    if (is_numeric($campaign_id)) {
      $stats_interactions = Location\Interaction::where('user_id', Core\Secure::userId())
        ->select(\DB::raw('DATE(created_at) as date'))
        ->where('campaign_id', $campaign_id)
        ->orderBy('date', 'asc')
        ->first();
    } elseif(is_array($campaign_id)) {
      $stats_interactions = Location\Interaction::where('user_id', Core\Secure::userId())
        ->select(\DB::raw('DATE(created_at) as date'))
        ->whereIn('campaign_id', $campaign_id)
        ->orderBy('date', 'asc')
        ->first();
    } else {
      $stats_interactions = Location\Interaction::where('user_id', Core\Secure::userId())
        ->select(\DB::raw('DATE(created_at) as date'))
        ->orderBy('date', 'asc')
        ->first();
    }

    if (count($stats_interactions) > 0) {
      $stats_found = true;
      if ($stats_interactions->date < $first_date) $first_date = $stats_interactions->date;
    }

    /*
     |--------------------------------------------------------------------------
     | Count views and interactions
     |--------------------------------------------------------------------------
     */

    // Card views
    // Raw query because of this issue: https://github.com/laravel/framework/issues/18523
    if (is_numeric($campaign_id)) {
      $stats_card_views = \DB::select("select DATE(created_at) as date, count(id) as views 
        from `card_stats` 
        where `user_id` = :user_id 
        and exists (select * from `campaigns` inner join `campaign_card` on `campaigns`.`id` = `campaign_card`.`campaign_id` where `campaign_card`.`campaign_id` in (:campaign_id)) 
        and `created_at` >= :from and `created_at` <= :to 
        group by DATE(created_at)", 
      [
        'user_id' => Core\Secure::userId(),
        'campaign_id' => $campaign_id,
        'from' => $from,
        'to' => $to
      ]);
    } elseif(is_array($campaign_id)) {
      $campaign_ids = implode(',', $campaign_id);
      $stats_card_views = \DB::select("select DATE(created_at) as date, count(id) as views 
        from `card_stats` 
        where `user_id` = :user_id 
        and exists (select * from `campaigns` inner join `campaign_card` on `campaigns`.`id` = `campaign_card`.`campaign_id` where `campaign_card`.`campaign_id` in (:campaign_ids)) 
        and `created_at` >= :from and `created_at` <= :to 
        group by DATE(created_at)", 
      [
        'user_id' => Core\Secure::userId(),
        'campaign_ids' => $campaign_ids,
        'from' => $from,
        'to' => $to
      ]);
    }  else {
      $stats_card_views = \DB::select("select DATE(created_at) as date, count(id) as views 
        from `card_stats` 
        where `user_id` = :user_id 
        and `created_at` >= :from and `created_at` <= :to 
        group by DATE(created_at)", 
      [
        'user_id' => Core\Secure::userId(),
        'from' => $from,
        'to' => $to
      ]);
    }

    /*
    $stats_card_views = ModelAnalytics\CardStat::where('user_id', Core\Secure::userId())
      ->whereHas('campaigns', function($query) use ($campaign_id) { 
        $query->whereIn('campaign_card.campaign_id', [$campaign_id]);     
      })
      ->select(\DB::raw('DATE(created_at) as date'), \DB::raw('count(id) as views'))
      ->where('created_at', '>=', $from)
      ->where('created_at', '<=', $to)
      ->groupBy([\DB::raw('DATE(created_at)')])
      ->get()
      ->toArray();
    */

    // Interactions
    if (is_numeric($campaign_id)) {
      $stats_interactions = Location\Interaction::where('user_id', Core\Secure::userId())
        ->select(\DB::raw('DATE(created_at) as date'), \DB::raw('count(id) as interactions'))
        ->where('campaign_id', $campaign_id)
        ->where('created_at', '>=', $from)
        ->where('created_at', '<=', $to)
        ->groupBy([\DB::raw('DATE(created_at)')])
        ->get()
        ->toArray();
    } elseif(is_array($campaign_id)) {
      $stats_interactions = Location\Interaction::where('user_id', Core\Secure::userId())
        ->select(\DB::raw('DATE(created_at) as date'), \DB::raw('count(id) as interactions'))
        ->whereIn('campaign_id', $campaign_id)
        ->where('created_at', '>=', $from)
        ->where('created_at', '<=', $to)
        ->groupBy([\DB::raw('DATE(created_at)')])
        ->get()
        ->toArray();
    } else {
      $stats_interactions = Location\Interaction::where('user_id', Core\Secure::userId())
        ->select(\DB::raw('DATE(created_at) as date'), \DB::raw('count(id) as interactions'))
        ->where('created_at', '>=', $from)
        ->where('created_at', '<=', $to)
        ->groupBy([\DB::raw('DATE(created_at)')])
        ->get()
        ->toArray();
    }

    // Create range for chart
    $main_chart_range = Analytics\AnalyticsController::getRange($date_start, $date_end);

    // Merge stats with range
    foreach($main_chart_range as $date => $arr) {

      // Views
      $views = ($date < $first_date) ? NULL : 0;
      foreach($stats_card_views as $row) {
        if ($date == $row->date) {
          $views = $row->views;
          break 1;
        }
      }

      $arr = array_merge(['views' => $views], $arr);

      // Interactions
      $interactions = 0;
      $interactions = ($date < $first_date) ? NULL : 0;
      foreach($stats_interactions as $row) {
        if ($date == $row['date']) {
          $interactions = $row['interactions'];
          break 1;
        }
      }

      $arr = array_merge(['interactions' => $interactions], $arr);
      $main_chart_range[$date] = $arr;
    }

    /*
     |--------------------------------------------------------------------------
     | Heatmap
     |--------------------------------------------------------------------------
     */

    // Card views
    // Raw query because of this issue: https://github.com/laravel/framework/issues/18523
    $heatmap_card_views = [];
    if (is_numeric($campaign_id)) {
      $heatmap_card_views = \DB::select("select lat, lng, count(id) as weight 
        from `card_stats` 
        where `user_id` = :user_id 
        and not isNull(lat) and not isNull(lng)
        and exists (select * from `campaigns` inner join `campaign_card` on `campaigns`.`id` = `campaign_card`.`campaign_id` where `campaign_card`.`campaign_id` in (:campaign_id)) 
        and `created_at` >= :from and `created_at` <= :to 
        group by lat, lng", 
      [
        'user_id' => Core\Secure::userId(),
        'campaign_id' => $campaign_id,
        'from' => $from,
        'to' => $to
      ]);
    } elseif(is_array($campaign_id)) {
      $campaign_ids = implode(',', $campaign_id);
      $heatmap_card_views = \DB::select("select lat, lng, count(id) as weight 
        from `card_stats` 
        where `user_id` = :user_id 
        and not isNull(lat) and not isNull(lng)
        and exists (select * from `campaigns` inner join `campaign_card` on `campaigns`.`id` = `campaign_card`.`campaign_id` where `campaign_card`.`campaign_id` in (:campaign_ids)) 
        and `created_at` >= :from and `created_at` <= :to 
        group by lat, lng", 
      [
        'user_id' => Core\Secure::userId(),
        'campaign_ids' => $campaign_ids,
        'from' => $from,
        'to' => $to
      ]);
    }  else {
      $heatmap_card_views = \DB::select("select lat, lng, count(id) as weight 
        from `card_stats` 
        where `user_id` = :user_id 
        and not isNull(lat) and not isNull(lng)
        and `created_at` >= :from and `created_at` <= :to 
        group by lat, lng", 
      [
        'user_id' => Core\Secure::userId(),
        'from' => $from,
        'to' => $to
      ]);
    }

    // Interactions
    $heatmap_interactions = [];
    if (is_numeric($campaign_id)) {
      $heatmap_interactions = Location\Interaction::where('user_id', Core\Secure::userId())
        ->select('lat', 'lng', \DB::raw('count(id) as weight'))
        ->where('campaign_id', $campaign_id)
        ->where('created_at', '>=', $from)
        ->where('created_at', '<=', $to)
        ->groupBy('lat')
        ->groupBy('lng')
        ->get()
        ->toArray();
    } elseif(is_array($campaign_id)) {
      $heatmap_interactions = Location\Interaction::where('user_id', Core\Secure::userId())
        ->select('lat', 'lng', \DB::raw('count(id) as weight'))
        ->whereIn('campaign_id', $campaign_id)
        ->where('created_at', '>=', $from)
        ->where('created_at', '<=', $to)
        ->groupBy('lat')
        ->groupBy('lng')
        ->get()
        ->toArray();
    } else {
      $heatmap_interactions = Location\Interaction::where('user_id', Core\Secure::userId())
        ->select('lat', 'lng', \DB::raw('count(id) as weight'))
        ->where('created_at', '>=', $from)
        ->where('created_at', '<=', $to)
        ->whereNotNull('lat')->whereNotNull('lng')
        ->groupBy('lat')->groupBy('lng')
        ->get()
        ->toArray();
    }

    $heatmap = [];
    
    foreach ($heatmap_card_views as $row) { $heatmap[] = ['lat' => $row->lat, 'lng' => $row->lng, 'weight' => $row->weight]; } 
    foreach ($heatmap_interactions as $row) { $heatmap[] = ['lat' => $row['lat'], 'lng' => $row['lng'], 'weight' => $row['weight']]; } 

    return view('platform.analytics.campaign-analytics', compact('sl', 'first_date', 'stats_found', 'date_start', 'date_end', 'campaigns', 'campaign_id', 'main_chart_range', 'heatmap'));
  }
}