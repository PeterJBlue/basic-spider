<?php // spider.php - Very crude spider - X05 - Peter Blue 2019

/*
This is VERY basic spider / web-crawler

It runs with PHP5 (should work on PHP7) on Linux or FreeBSD. It might work on Windows but I haven't tested it.

It crudely obeys the robots.txt file

There is some sanity checking (on URLs & HTML) but not much.

To run / test :-

1. Ensure you have PHP installed and ready to run.
2. Go to the command line and nivigate to where you have this project.
3. Type: php spider.php
4. It will display various details about the websites that it's downloading from.
5. It will deposit all links (one per line) to file: captured-urls.txt
6. Each time you run this it will discover more links.

Have fun !

*/

// Development :-
// X01 - Basic spider starting point from previous code. Just grab one domain and scan for links
// X02 - Improve various functions
// X03 - Add multiple passes
// X04 - Obey robots.txt ... sort of
// X05 - Minor fixes and improvements

// General settings
$AgentString  = "UltraBasicBot";
$CrawlDelay   = 1;  // Seconds
$CrawlTimeout = 10; // [X05] Timeout in seconds
$PathToLinks  = "captured-urls.txt"; // Output file, one URL per line.



// Change agent string to something a bit nicer and informative
$options  = array('http' => array('user_agent' => $AgentString, 'timeout' => $CrawlTimeout));
$context  = stream_context_create($options); // This gets used by file_get_contents()

// Get links saved from previous session
$size = (int) @filesize($PathToLinks);
if ($size > 0)
  { // Link file found -> load
  $LinkList = file($PathToLinks,FILE_IGNORE_NEW_LINES);
  }
else
  { // Link file NOT found -> create starter
  $ThisDomain = "https://directory.si7.uk";
  $LinkList[] = $ThisDomain;
  }

$PrevDomain   = ""; // Previous domain

echo "Spider Version :  X04 - Basic Spider \n";
echo "Agent String   :  $AgentString \n";
echo "Crawl Delay    :  $CrawlDelay seconds\n";
echo "Crawl Timeout  :  $CrawlTimeout seconds\n"; // [X05]

$allow = true; // We're allowd to crawl this domain
$flag  = 0;
$lcnt  = count($LinkList);
for ($l=0; $l<$lcnt; $l++)
  { // Scan all links in this array
  $flag  = 0; // [X05] Not new domain
  $link  = $LinkList[$l]; // Get current URL
  
  if ($ThisDomain == $PrevDomain)
    { // Same domain as before - sleep to save server stress
    sleep($CrawlDelay); // ToDo: Make random
    }
  else
    { // New domain -> get robots.txt
    $PrevDomain = $ThisDomain;
    $flag = 1; // Indicate new domain
    }
    
  $ptr1 = (int) strpos($link,"://");       // Point past protocol
  $ptr2 = (int) strpos($link,"/",$ptr1+3); // Point to end of domain, eg https://domain.com/page.html
  if ($ptr2 > 0) { $ThisDomain = substr($link,0,$ptr2); } // Remove page from domain
  else { $ThisDomain = $link; } // Copy as-is
  
  if ($flag > 0)
    {
    $robfn = "$ThisDomain/robots.txt";
    $robot = strtolower(@file_get_contents($robfn));
    echo "Robots.txt     :  ".strlen($robot)." bytes -> $robfn \n";
    }
  
  echo "Link URL       :  $link , Domain:($ThisDomain) \n";
  
  // Very crude robots parser
  $allow = true;
  if ( stripos($robot,"user-agent: $AgentString\ndisallow: /\n") !== false ) { $allow = false; } // Disallow this agent
  if ( stripos($robot,"user-agent: *\ndisallow: /\n") !== false ) { $allow = false; } // Disallow all agents
  
  if ($allow)
    {
    $html = @file_get_contents($link,false,$context);
    $hlen = strlen($html);
    echo "Page size      :  $hlen bytes ";
    if ($hlen > 10)
      {
      echo "\n";
      
      list($err,$head,$body) = htmlGetHeaderAndBody($html); // Split HTML into Header and Body
      
      $new  = ExtractLinks($body,$ThisDomain);
      $ncnt = count($new);
      echo "Links added    :  $ncnt \n";
      for ($i=0; $i<$ncnt; $i++) { echo "-> ".$new[$i]." \n"; } // Show links to user
      }
    else
      {
      echo " - URL not reachable !\n";
      }
    } // if allow
  else
    {
    echo "Not allowed to crawl this domain !!\n";
    //sleep(2);
    }
  } // for l

echo "Task complete.\nIf you run this again you will get more links.\n\n";
  
// ---- Write out links to file -----------------------------------------------
$data = "";
$lcnt = count($LinkList);
for ($i=0; $i<$lcnt; $i++)
  {
  $link = $LinkList[$i];
  $data .= "$link\n";
  }

file_put_contents($PathToLinks,$data);





// ---- Basic functions -------------------------------------------------------

// <A HREF="xxx">link1</A>
// <a rel="" href="zzz" style="">
function ExtractLinks($body,$domain)
  {
  global $LinkList;
  
  $new  = null;
  $blen = strlen($body);
  
  $ptr1 = (int) stripos($body,"<a "); // Find first link in body text
  while ($ptr1 > 0)
    { // Link found -> continue
    $ptr3 = $blen; // Safe default
    $ptr2 = (int) stripos($body,"href=",$ptr1); // Find href=
    if ($ptr2 > $ptr1)
      { // Sanity check
      $ptr2 = $ptr2 + 5; // Step over href=
      $ptr3 = (int) stripos($body,">",$ptr2); // Find end of tag
      
      $snip = substr($body,$ptr2,$ptr3-$ptr2); // Get snippet
      $quot = $snip[0]; // Char used to quote URL
      $ptr4 = strpos($snip,$quot,1); // Find end quote char
      if ($ptr4 > 0)
        { // End quote found -> clip
        $url1 = substr($snip,1,$ptr4-1); // Get link from this snippet
        $url2 = NormaliseURL($url1,$domain);
        $sane = SanintyCheckURL($url2);
        if ($sane)
          { // Sanity check = OK
          $ok   = in_array($url2,$LinkList); // Is this URL unique ?
          if ($ok == false) { $LinkList[] = $url2; $new[] = $url2; } // Yes -> add to list & add to new list
          }
        }
      }
      
    $ptr1 = (int) stripos($body,"<a ",$ptr3); // Go round again
    }
  
  return $new; // Return only new URLs, the others are in $LinkList
  }


// Many links are relative, some are external so we make them uniform - makes searching for dups much easier
// Examples :-
//   index.php
//   /about-us
//   art.php?view=1701
//   stuff.html#apple
//   http://other-domain.com
function NormaliseURL($url,$domain)
  {
  // Check for external links
  if (substr($url,0,7) != "http://" && substr($url,0,8) != "https://")
    { // Internal (relative) link -> re-form
    if ($url[0] == "/") { $url = substr($url,1); } // Remove leading /
    $url = "$domain/$url"; // Re-form URL
    }
  
  // Remove anchor - it's the same page
  $ptr1 = (int) strpos($url,"#");
  if ($ptr1 > 0) { $url = substr($url,0,$ptr1); }
  
  return trim($url);
  }


// Sanity check for normalised URL - there some really weird ones out there !
function SanintyCheckURL($url)
  {
  $sane = true;
  
  // Check for blank URL
  if (strlen($url) < 4) { $sane = false; }
  
  // Check for spaces
  $ptr = (int) strpos($url, " ");
  if ($ptr > 0) { $sane = false; }
  
  return $sane;
  }


// Grabbed from html-lib-400.inc

// Split HTML into header and body sections
function htmlGetHeaderAndBody($html)
  {
  $head = "";
  $body = "";
  $err  = 1;
  
  $htm2 = htmlExtractBetweenTags($html,"<html","</html>");
  
  $ptr  = (int) stripos($htm2,"<body");
  //echo " [GHaB][HTM2:$htm2][PTR:$ptr] ";
  
  if ($ptr > 0)
    {
    $head = htmlExtractBetweenTags($html,"<head","</head>");
    $body = htmlExtractBetweenTags($html,"<body","</body>");
    $err = 0;
    }
  
  return array($err,$head,$body);
  }

function htmlExtractBetween($data,$tag1,$tag2)
  {
  $out  = "";
  $len1 = strlen($tag1);
  $ptr1 = stripos($data,$tag1); // Find 1st tag
  if ($ptr1 !== false)
    {
    $ptr1 = $ptr1 + $len1; // Step over 1st tag
    $ptr2 = stripos($data,$tag2,$ptr1); // Find 2nd tag
    if ($ptr2 > 0)
      {
      $out = substr($data,$ptr1,($ptr2-$ptr1));
      }
    }
    
  return $out;
  }

// $tag1 = "<html lang='en'  >"
// $tag2 = "</html>";
function htmlExtractBetweenTags($data,$tag1,$tag2)
  {
  $out  = "";
  $dlen = (int) strlen($data);
  $len1 = (int) strlen($tag1);
  $ptr1 = (int) stripos($data,$tag1); // Find 1st tag
  //echo " [EBT][DLEN:$dlen][TAG1:$tag1][PTR1:$ptr1] ";
  //if ($ptr1 > 0)
  if ($ptr1 !== false)
    {
    $ptr3 = stripos($data,">",$ptr1); // Find end of 1st tag eg ">"
    if ($ptr3 > 0) { $ptr1 = $ptr3; } // Found ">"
    $ptr2 = stripos($data,$tag2,$ptr1); // Find 2nd tag
    if ($ptr2 > 0)
      {
      $ptr1++; // Step over ">"
      $out = substr($data,$ptr1,($ptr2-$ptr1));
      }
    }
    
  return trim($out);
  }


//           1111111111
// 01234567890123456789
// http://x.uk

?>
