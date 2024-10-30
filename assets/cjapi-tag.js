(async function(a,b,c,d){
    if (!window.cj) window.cj = {};

    if (! cj_from_php.post_id){
        cj_from_php.post_id = '';
    } else if (isNaN(cj_from_php.post_id) || +cj_from_php.post_id < 1){
        throw Error('Failed to add CJ Affiliate Tracking code due to receiving the invalid post ID: ' + cj_from_php.post_id)
    }

    let use_conversion_tag = cj_from_php.tag_type === 'conversion_tag'
    let action = use_conversion_tag ? 'cj_conversion_tag_data' : 'cj_site_tag_data'
    let source_action = 'cj_source_data'

    let url = cj_from_php.ajaxurl + '?action=' + action + '&post_id=' + cj_from_php.post_id
    let source_url = cj_from_php.ajaxurl + '?action=' + source_action + '&post_id=' + cj_from_php.post_id

    if (use_conversion_tag){
        //let order_received = (new URL(window.location.href)).searchParams.get("order-received");
        let order_received = cj_from_php.woo_order_id;
        url += '&order-received=' + order_received
    }

    let resp = await fetch(url)
    if ( ! resp.ok)
        throw Error('Error retrieving conversion tag data')
    let data = await resp.json()

    let cj_prop = use_conversion_tag ? 'order' : 'sitePage'
    window.cj[cj_prop] = data

    // Get cj.source object data by calling the file
    let source_resp = await fetch(source_url)
    if ( ! source_resp.ok)
        throw Error('Error retrieving source data')
    let source_data = await source_resp.json()


    let cj_source = 'source'
    window.cj[cj_source] = source_data

    //url = cj_from_php.ajaxurl + '?action=cjapi_com_js'

    url = 'https://www.mczbf.com/tags/' + cj_from_php.tag_id + '/tag.js'

    a=url;
    b=document;c='script';d=b.createElement(c);d.src=a;
    d.type='text/java'+c;d.async=true;
    d.id='cjapitag';
    a=b.getElementsByTagName(c)[0];a.parentNode.insertBefore(d,a)

    //TODO USELESS CODE
    // below code must be data.sendOrderOnLoad to actually work
    /*if (cj_from_php.sendOrderOnLoad){
        window.addEventListener('load', function buildAndSendOrder(){
        if (cj.order)
            cjApi.sendOrder(cj.order)
        });
    }*/

})();
