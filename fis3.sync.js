/**
 * @file fis conf
 * @author kaivean(kaivean@outlook.com)
 */

fis.match('**', {
    deploy: fis.plugin('http-push', {
        receiver: '/fisrec.php',
        // to: '/home/work/odp/jet',
        to: '/home/work/odp/jet'
    })
});
